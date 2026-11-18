<?php

namespace Tests\Filament\Resources;

use App\Enums\ProspectClassification;
use App\Enums\ProspectStatus;
use App\Events\RecordPipelineStatusChanged;
use App\Filament\Forms\Components\GalleryPhotoCarousel;
use App\Filament\Resources\Prospects\Pages\ManageProspects;
use App\Filament\Resources\Prospects\ProspectResource;
use App\Mail\ProspectReplyMail;
use App\Models\GalleryPhoto;
use App\Models\Page;
use App\Models\Prospect;
use App\Models\User;
use App\Services\Prospects\ProspectReplyAttachmentService;
use Filament\Actions\Testing\TestAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Generator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ProspectResource::class)]
class ProspectResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public static function roleProvider(): Generator
    {
        yield 'admin' => [true, 200];
        yield 'editor' => [false, 403];
    }

    #[Test]
    #[DataProvider('roleProvider')]
    public function it_restricts_the_leads_page_to_admins(bool $isAdmin, int $expectedStatus): void
    {
        $user = $isAdmin ? User::factory()->admin()->create() : User::factory()->create();

        $response = $this->actingAs($user)->get(ProspectResource::getUrl('index'));

        $response->assertStatus($expectedStatus);
    }

    #[Test]
    public function it_lists_prospects_for_admins(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $prospects = Prospect::factory()->count(2)->create();

        Livewire::test(ManageProspects::class)->assertCanSeeTableRecords($prospects);
    }

    #[Test]
    public function it_defaults_to_newest_submitted_first_regardless_of_status(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $newestOther = Prospect::factory()->create([
            'status' => ProspectStatus::Replied,
            'submitted_at' => now(),
        ]);
        $newerAnswerReview = Prospect::factory()->draftReady()->create(['submitted_at' => now()->subMinutes(30)]);
        $newerHumanReview = Prospect::factory()->pendingReview()->create(['submitted_at' => now()->subHour()]);
        $olderAnswerReview = Prospect::factory()->draftReady()->create(['submitted_at' => now()->subDays(2)]);
        $olderHumanReview = Prospect::factory()->pendingReview()->create(['submitted_at' => now()->subDays(3)]);
        $oldestOther = Prospect::factory()->create([
            'status' => ProspectStatus::Unanswerable,
            'submitted_at' => now()->subDays(4),
        ]);

        Livewire::test(ManageProspects::class)
            ->assertCanSeeTableRecords([
                $newestOther,
                $newerAnswerReview,
                $newerHumanReview,
                $olderAnswerReview,
                $olderHumanReview,
                $oldestOther,
            ], inOrder: true);
    }

    #[Test]
    public function it_keeps_a_prospects_table_position_stable_across_a_status_change(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $newer = Prospect::factory()->pendingReview()->create(['submitted_at' => now()]);
        $older = Prospect::factory()->pendingReview()->create(['submitted_at' => now()->subHour()]);
        $originalSubmittedAt = $older->submitted_at;

        $older->markStatus(ProspectStatus::Unanswerable, auth()->id());
        $older->refresh();

        Livewire::test(ManageProspects::class)
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
        $this->assertEquals($originalSubmittedAt, $older->submitted_at);
    }

    #[Test]
    public function it_hides_a_prospect_when_either_ai_or_human_classifies_it_as_spam(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $humanSpam = Prospect::factory()->create([
            'classification' => ProspectClassification::NeedsHuman,
            'human_classification' => ProspectClassification::Spam,
        ]);
        $aiSpam = Prospect::factory()->create([
            'classification' => ProspectClassification::Spam,
            'human_classification' => null,
        ]);
        $needsReview = Prospect::factory()->create([
            'classification' => ProspectClassification::NeedsHuman,
            'human_classification' => null,
        ]);

        Livewire::test(ManageProspects::class)
            ->assertCanSeeTableRecords([$needsReview])
            ->assertCanNotSeeTableRecords([$humanSpam, $aiSpam]);
    }

    #[Test]
    public function it_places_the_review_action_before_the_data_columns(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $component = Livewire::test(ManageProspects::class);

        $this->assertSame(
            RecordActionsPosition::BeforeColumns,
            $component->instance()->getTable()->getRecordActionsPosition(),
        );

        $actions = $component->instance()->getTable()->getFlatRecordActions();

        $this->assertSame(['review'], array_keys($actions));
        $this->assertInstanceOf(ViewAction::class, $actions['review']);
        $this->assertSame('Review', $actions['review']->getLabel());
    }

    #[Test]
    public function it_limits_long_names_in_the_table(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $component = Livewire::test(ManageProspects::class);

        $this->assertSame(50, $component->instance()->getTable()->getColumn('name')->getCharacterLimit());
    }

    #[Test]
    public function it_shows_the_review_indicator_for_the_main_prospect_states(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $readyForReview = Prospect::factory()->pendingReview()->create();
        $answerReviewPending = Prospect::factory()->draftReady()->create();
        $messageSent = Prospect::factory()->create(['status' => ProspectStatus::Replied]);
        $noActionNeeded = Prospect::factory()->spam()->create();
        $classifying = Prospect::factory()->create();
        $drafting = Prospect::factory()->create(['status' => ProspectStatus::DraftGenerating]);

        $component = Livewire::test(ManageProspects::class)
            ->assertTableColumnStateSet('review_indicator', 'ready_for_review', record: $readyForReview)
            ->assertTableColumnStateSet('review_indicator', 'answer_review_pending', record: $answerReviewPending)
            ->assertTableColumnStateSet('review_indicator', 'message_sent', record: $messageSent)
            ->assertTableColumnStateSet('review_indicator', 'no_action_needed', record: $noActionNeeded)
            ->assertTableColumnStateSet('review_indicator', 'classifying', record: $classifying)
            ->assertTableColumnStateSet('review_indicator', 'drafting', record: $drafting);

        $indicator = $component->instance()->getTable()->getColumn('review_indicator');
        $this->assertSame('warning', $indicator->getColor('answer_review_pending'));
    }

    #[Test]
    public function it_keeps_the_readable_pipeline_status_column_alongside_the_indicator(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $component = Livewire::test(ManageProspects::class);

        $this->assertSame('Pipeline status', $component->instance()->getTable()->getColumn('status')->getLabel());
    }

    #[Test]
    public function it_filters_the_table_by_status(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $spam = Prospect::factory()->spam()->create();
        $pending = Prospect::factory()->create();

        Livewire::test(ManageProspects::class)
            ->filterTable('status', ProspectStatus::Spam->value)
            ->assertCanSeeTableRecords([$spam])
            ->assertCanNotSeeTableRecords([$pending]);
    }

    #[Test]
    public function it_only_offers_human_meaningful_status_filters(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageProspects::class)
            ->assertTableFilterExists('status', function (SelectFilter $filter): bool {
                return array_keys($filter->getOptions()) === [
                    ProspectStatus::PendingReview->value,
                    ProspectStatus::DraftReady->value,
                    ProspectStatus::Spam->value,
                    ProspectStatus::Unanswerable->value,
                    ProspectStatus::Replied->value,
                ];
            });
    }

    #[Test]
    public function it_formats_prospect_interests_for_the_view_action(): void
    {
        $this->assertSame('Wedding', ProspectResource::formatInterests('Wedding'));
        $this->assertSame('Corporate Event', ProspectResource::formatInterests('Corporate Event'));
        $this->assertSame('Wedding, Corporate Event', ProspectResource::formatInterests('["Wedding","Corporate Event"]'));
    }

    #[Test]
    public function it_distinguishes_human_review_from_the_ai_classification(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Prospect::factory()->pendingReview()->create();

        Livewire::test(ManageProspects::class)
            ->assertSee('Human review')
            ->assertSee('AI classification')
            ->assertSee('Pending')
            ->assertSee('Valid');
    }

    #[Test]
    public function it_opens_the_view_action_for_a_prospect_without_interests(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $prospect = Prospect::factory()->create(['interests' => null]);
        $component = Livewire::test(ManageProspects::class);
        $reviewAction = $component->instance()->getTable()->getRecordActions()[0];

        $this->assertInstanceOf(ViewAction::class, $reviewAction);
        $this->assertSame('Review', $reviewAction->getLabel());
        $this->assertSame(Heroicon::Cog6Tooth, $reviewAction->getIcon());
        $this->assertTrue($reviewAction->isModalSlideOver());

        $component->callTableAction('review', $prospect)->assertOk();
    }

    #[Test]
    public function it_opens_the_view_action_for_a_draft_ready_prospect(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $prospect = Prospect::factory()->draftReady()->create();

        Livewire::test(ManageProspects::class)
            ->callTableAction('review', $prospect)
            ->assertOk();
    }

    #[Test]
    public function it_shows_the_active_status_spinner_in_the_open_prospect_view(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $prospect = Prospect::factory()->pendingReview()->create([
            'status' => ProspectStatus::DraftGenerating,
        ]);

        Livewire::test(ManageProspects::class)
            ->callTableAction('review', $prospect)
            ->assertSee('Drafting reply')
            ->assertSeeHtml('animation: dwc-status-spin');
    }

    #[Test]
    public function it_subscribes_to_the_fixed_pipeline_channel_regardless_of_which_record_is_open(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $listeners = (new ManageProspects)->getListeners();

        $this->assertSame(['echo-private:prospects-pipeline,.status.changed' => '$refresh'], $listeners);
    }

    /**
     * @return list<TestAction>
     */
    private function replyAction(string $name, Prospect $prospect): array
    {
        return [
            TestAction::make('review')->table($prospect),
            TestAction::make($name)->schemaComponent(true, 'mountedActionSchema0'),
        ];
    }

    private function galleryPhotoWithImage(): GalleryPhoto
    {
        $photo = GalleryPhoto::factory()->create();
        Storage::disk('public')->put($photo->image, UploadedFile::fake()->image('photo.jpg', 400, 300)->getContent());

        return $photo;
    }

    #[Test]
    public function it_saves_the_chosen_gallery_photos_and_uploads_when_editing_a_reply(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
        $prospect = Prospect::factory()->draftReady()->create();
        $photo = $this->galleryPhotoWithImage();

        Livewire::test(ManageProspects::class)
            ->callAction($this->replyAction('editDraftReply', $prospect), [
                'final_reply' => 'Edited reply',
                'website_images' => [$photo->image],
                'uploads' => [UploadedFile::fake()->image('stage.png', 200, 200)],
            ])
            ->assertHasNoFormErrors();

        $prospect->refresh();
        $upload = $prospect->reply_attachments[1] ?? null;
        $this->assertSame('Edited reply', $prospect->final_reply);
        $this->assertEquals(['disk' => 'public', 'path' => $photo->image, 'name' => basename($photo->image)], $prospect->reply_attachments[0]);
        $this->assertSame(['local', 'stage.png'], [$upload['disk'] ?? null, $upload['name'] ?? null]);
        $this->assertCount(2, $prospect->reply_attachments);
    }

    public static function stagingOrderProvider(): Generator
    {
        yield 'upload, then a website photo' => [true, ['local', 'public']];
        yield 'website photo, then an upload' => [false, ['public', 'local']];
    }

    /**
     * @param  list<string>  $expectedDisks
     */
    #[Test]
    #[DataProvider('stagingOrderProvider')]
    public function it_saves_attachments_in_the_order_they_were_staged(bool $uploadsFirst, array $expectedDisks): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
        $prospect = Prospect::factory()->draftReady()->create();
        $photo = $this->galleryPhotoWithImage();
        $component = Livewire::test(ManageProspects::class)->mountAction($this->replyAction('editDraftReply', $prospect));

        if ($uploadsFirst) {
            $component->set('mountedActions.1.data.uploads.stage-key', UploadedFile::fake()->image('stage.png', 200, 200));
        }

        $component->fillForm(['website_images' => [...$component->get('mountedActions.1.data.website_images'), $photo->image]]);

        if (! $uploadsFirst) {
            $component->set('mountedActions.1.data.uploads.stage-key', UploadedFile::fake()->image('stage.png', 200, 200));
        }

        $component->callMountedAction()->assertHasNoFormErrors();

        $this->assertSame($expectedDisks, array_column($prospect->refresh()->reply_attachments, 'disk'));
    }

    #[Test]
    public function it_keeps_the_saved_attachment_order_when_a_reply_is_reopened_and_saved(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
        $photo = $this->galleryPhotoWithImage();
        $uploadPath = 'prospect-replies/1/stage.png';
        Storage::disk('local')->put($uploadPath, UploadedFile::fake()->image('stage.png', 200, 200)->getContent());
        $attachments = [
            ['disk' => 'local', 'path' => $uploadPath, 'name' => 'stage.png'],
            ['disk' => 'public', 'path' => $photo->image, 'name' => basename($photo->image)],
        ];
        $prospect = Prospect::factory()->draftReady()->create(['reply_attachments' => $attachments]);

        Livewire::test(ManageProspects::class)
            ->callAction($this->replyAction('editDraftReply', $prospect))
            ->assertHasNoFormErrors();

        $this->assertEquals($attachments, $prospect->refresh()->reply_attachments);
    }

    #[Test]
    public function it_stores_reply_uploads_privately_on_the_local_disk_under_the_prospect_folder(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
        $prospect = Prospect::factory()->draftReady()->create();

        Livewire::test(ManageProspects::class)
            ->callAction($this->replyAction('editDraftReply', $prospect), [
                'final_reply' => 'Edited reply',
                'uploads' => [UploadedFile::fake()->image('stage.jpg', 200, 200)],
            ]);

        $path = $prospect->refresh()->reply_attachments[0]['path'];
        $this->assertStringStartsWith("prospect-replies/{$prospect->id}/", $path);
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public static function replyActionProvider(): Generator
    {
        yield 'edit reply' => ['editDraftReply'];
    }

    #[Test]
    #[DataProvider('replyActionProvider')]
    public function it_picks_gallery_photos_with_the_carousel_field_in_both_reply_actions(string $action): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $prospect = Prospect::factory()->draftReady()->create();
        GalleryPhoto::factory()->create();

        $component = Livewire::test(ManageProspects::class)
            ->mountAction($this->replyAction($action, $prospect))
            ->assertFormFieldExists('website_images', fn (mixed $field): bool => $field instanceof GalleryPhotoCarousel);

        $this->assertStringContainsString('gpc-track', implode('', $component->effects['partials'] ?? []));
    }

    #[Test]
    public function it_saves_a_picked_page_image_and_attaches_it_on_approve_and_send(): void
    {
        Mail::fake();
        Storage::fake('local');
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
        Storage::disk('public')->put('pages/about-circle.jpg', UploadedFile::fake()->image('circle.jpg', 300, 300)->getContent());
        Page::factory()->create(['title' => 'About', 'circle_image' => 'pages/about-circle.jpg']);
        $prospect = Prospect::factory()->draftReady()->create(['reply_attachments' => null]);

        Livewire::test(ManageProspects::class)
            ->callAction($this->replyAction('editDraftReply', $prospect), [
                'final_reply' => 'Edited reply',
                'website_images' => ['pages/about-circle.jpg'],
            ])
            ->assertHasNoFormErrors();
        Livewire::test(ManageProspects::class)
            ->callAction($this->replyAction('approveEditedAndSend', $prospect->refresh()))
            ->assertHasNoActionErrors();

        $this->assertEquals([['disk' => 'public', 'path' => 'pages/about-circle.jpg', 'name' => 'about-circle.jpg']], $prospect->refresh()->reply_attachments);
        $this->assertSame(ProspectStatus::Replied, $prospect->status);
        Mail::assertSent(ProspectReplyMail::class, fn (ProspectReplyMail $mail): bool => count($mail->attachments()) === 1);
    }

    /**
     * @param  list<GalleryPhoto>|Collection<int, GalleryPhoto>  $photos
     * @return list<array{disk: string, path: string, name: string}>
     */
    private function galleryEntries(iterable $photos): array
    {
        return collect($photos)
            ->map(fn (GalleryPhoto $photo): array => ['disk' => 'public', 'path' => $photo->image, 'name' => basename($photo->image)])
            ->values()
            ->all();
    }

    #[Test]
    public function it_has_no_form_and_asks_for_confirmation_when_approving_and_sending(): void
    {
        Mail::fake();
        $this->actingAs(User::factory()->admin()->create());
        $prospect = Prospect::factory()->draftReady()->create(['name' => 'Jane Lead', 'final_reply' => null, 'reply_attachments' => $this->galleryEntries([GalleryPhoto::factory()->create()])]);

        $component = Livewire::test(ManageProspects::class)
            ->mountAction($this->replyAction('approveDraftAndSend', $prospect))
            ->assertActionMounted($this->replyAction('approveDraftAndSend', $prospect));

        $modal = implode('', $component->effects['partials'] ?? []);
        $this->assertStringContainsString('Send this reply to Jane Lead?', $modal);
        $this->assertStringContainsString('1 image', $modal);
        $this->assertStringNotContainsString('gpc-track', $modal);
        $this->assertStringNotContainsString('<textarea', $modal);

        Mail::assertNothingSent();
    }

    #[Test]
    public function it_sends_the_saved_images_and_the_ai_draft_when_nothing_was_edited(): void
    {
        Mail::fake();
        Storage::fake('local');
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
        $photos = collect([$this->galleryPhotoWithImage(), $this->galleryPhotoWithImage()]);
        $prospect = Prospect::factory()->draftReady()->create([
            'generated_reply' => 'The AI draft',
            'final_reply' => null,
            'reply_attachments' => $this->galleryEntries($photos),
        ]);

        Livewire::test(ManageProspects::class)
            ->callAction($this->replyAction('approveDraftAndSend', $prospect))
            ->assertHasNoActionErrors();

        $prospect->refresh();
        $this->assertSame(ProspectStatus::Replied, $prospect->status);
        $this->assertSame('The AI draft', $prospect->final_reply);
        Mail::assertSent(ProspectReplyMail::class, 1);
        Mail::assertSent(ProspectReplyMail::class, fn (ProspectReplyMail $mail): bool => count($mail->attachments()) === 2
            && str_contains($mail->render(), 'The AI draft'));
    }

    #[Test]
    public function it_sends_the_saved_edited_reply_when_one_exists(): void
    {
        Mail::fake();
        $this->actingAs(User::factory()->admin()->create());
        $prospect = Prospect::factory()->draftReady()->create([
            'generated_reply' => 'The AI draft',
            'final_reply' => 'My edited reply',
        ]);

        Livewire::test(ManageProspects::class)
            ->callAction($this->replyAction('approveEditedAndSend', $prospect));

        $this->assertSame(ProspectStatus::Replied, $prospect->refresh()->status);
        Mail::assertSent(ProspectReplyMail::class, fn (ProspectReplyMail $mail): bool => str_contains($mail->render(), 'My edited reply')
            && ! str_contains($mail->render(), 'The AI draft')
            && $mail->attachments() === []);
    }

    #[Test]
    public function it_rejects_approve_and_send_with_more_than_ten_saved_images(): void
    {
        Mail::fake();
        Storage::fake('local');
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
        $photos = GalleryPhoto::factory()->count(11)->create();
        $prospect = Prospect::factory()->draftReady()->create(['reply_attachments' => $this->galleryEntries($photos)]);

        Livewire::test(ManageProspects::class)
            ->callAction($this->replyAction('approveDraftAndSend', $prospect))
            ->assertNotified('Reply not sent');

        $this->assertSame(ProspectStatus::DraftReady, $prospect->refresh()->status);
        Mail::assertNothingSent();
    }

    #[Test]
    public function it_rejects_approve_and_send_when_the_images_exceed_the_size_cap(): void
    {
        Mail::fake();
        Storage::fake('local');
        Storage::fake('public');
        $this->app->instance(ProspectReplyAttachmentService::class, new ProspectReplyAttachmentService(maxTotalBytes: 10));
        $this->actingAs(User::factory()->admin()->create());
        $photo = $this->galleryPhotoWithImage();
        $prospect = Prospect::factory()->draftReady()->create(['reply_attachments' => $this->galleryEntries([$photo])]);

        Livewire::test(ManageProspects::class)
            ->callAction($this->replyAction('approveDraftAndSend', $prospect))
            ->assertNotified('Reply not sent');

        $this->assertSame(ProspectStatus::DraftReady, $prospect->refresh()->status);
        Mail::assertNothingSent();
    }

    #[Test]
    public function it_sends_an_approved_reply_without_attachments(): void
    {
        Mail::fake();
        $this->actingAs(User::factory()->admin()->create());
        $prospect = Prospect::factory()->draftReady()->create(['reply_attachments' => null]);

        Livewire::test(ManageProspects::class)
            ->callAction($this->replyAction('approveDraftAndSend', $prospect))
            ->assertHasNoActionErrors();

        $this->assertSame(ProspectStatus::Replied, $prospect->refresh()->status);
        Mail::assertSent(ProspectReplyMail::class, fn (ProspectReplyMail $mail): bool => $mail->attachments() === []);
    }

    #[Test]
    public function it_places_the_set_status_column_right_after_the_pipeline_status_column(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $columnNames = array_keys(Livewire::test(ManageProspects::class)->instance()->getTable()->getColumns());

        $statusIndex = array_search('status', $columnNames, true);

        $this->assertNotFalse($statusIndex);
        $this->assertSame('set_status', $columnNames[$statusIndex + 1]);
    }

    #[Test]
    public function it_sets_status_manually_without_sending_or_generating(): void
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);
        Mail::fake();
        $prospect = Prospect::factory()->draftReady()->withReplyAttachments()->create();
        Queue::fake();
        Event::fake([RecordPipelineStatusChanged::class]);

        Livewire::test(ManageProspects::class)
            ->callAction(TestAction::make('setStatus')->table($prospect), ['status' => ProspectStatus::Replied->value])
            ->assertHasNoTableActionErrors();

        $prospect->refresh();
        $this->assertSame(ProspectStatus::Replied, $prospect->status);
        $this->assertSame($user->id, $prospect->reviewed_by_user_id);
        $this->assertNull($prospect->replied_at);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Queue::assertNothingPushed();
        Event::assertDispatched(RecordPipelineStatusChanged::class);
    }
}
