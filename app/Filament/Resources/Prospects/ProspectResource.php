<?php

namespace App\Filament\Resources\Prospects;

use App\Enums\ProspectStatus;
use App\Filament\Forms\Components\GalleryPhotoCarousel;
use App\Filament\Resources\Prospects\Pages\ManageProspects;
use App\Models\Prospect;
use App\Services\Prospects\ProspectReplyAttachmentData;
use App\Services\Prospects\ProspectReplyAttachmentService;
use App\Services\Prospects\ProspectReplyAttachmentsRejectedException;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

class ProspectResource extends Resource
{
    protected static ?string $model = Prospect::class;

    protected static string|UnitEnum|null $navigationGroup = 'Leads';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 10;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query): Builder => self::applyDefaultTableSort($query))
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->notSpam())
            ->columns([
                IconColumn::make('review_indicator')
                    ->label('')
                    ->state(fn (Prospect $record): string => match ($record->status) {
                        ProspectStatus::PendingReview => 'ready_for_review',
                        ProspectStatus::DraftReady => 'answer_review_pending',
                        ProspectStatus::Replied => 'message_sent',
                        ProspectStatus::Spam, ProspectStatus::Unanswerable => 'no_action_needed',
                        ProspectStatus::PendingClassification => 'classifying',
                        ProspectStatus::Valid, ProspectStatus::DraftGenerating => 'drafting',
                    })
                    ->icon(fn (string $state): BackedEnum|HtmlString => match ($state) {
                        'ready_for_review' => Heroicon::Clock,
                        'answer_review_pending' => Heroicon::EnvelopeOpen,
                        'message_sent' => Heroicon::Envelope,
                        'no_action_needed' => Heroicon::EnvelopeOpen,
                        'classifying', 'drafting' => self::spinningStatusIcon(),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'ready_for_review', 'answer_review_pending' => 'warning',
                        'classifying', 'drafting' => 'info',
                        'message_sent' => 'success',
                        'no_action_needed' => 'gray',
                    })
                    ->tooltip(fn (string $state): string => match ($state) {
                        'ready_for_review' => 'Ready for review',
                        'answer_review_pending' => 'Answer review pending',
                        'message_sent' => 'Message sent',
                        'no_action_needed' => 'No action needed',
                        'classifying' => 'Classifying prospect',
                        'drafting' => 'Drafting reply',
                    }),
                TextColumn::make('name')
                    ->limit(50)
                    ->tooltip(fn (Prospect $record): ?string => mb_strlen($record->name ?? '') > 50 ? $record->name : null)
                    ->description(fn (Prospect $record): ?string => trim(($record->email ?? '').($record->phone ? ' · '.$record->phone : '')) ?: null)
                    ->searchable(),
                TextColumn::make('human_classification')
                    ->label('Human review')
                    ->badge()
                    ->placeholder('Pending'),
                TextColumn::make('classification')
                    ->label('AI classification')
                    ->badge()
                    ->placeholder('Pending'),
                TextColumn::make('status')
                    ->label('Pipeline status')
                    ->badge(),
                TextColumn::make('set_status')
                    ->label('')
                    ->state('Set status')
                    ->icon(Heroicon::ArrowPath)
                    ->action(
                        Action::make('setStatus')
                            ->label('Set status')
                            ->icon(Heroicon::ArrowPath)
                            ->modalHeading('Set status manually?')
                            ->modalDescription('This only changes the status. No email is sent and no reply is generated.')
                            ->modalSubmitActionLabel('Save status')
                            ->schema([
                                Select::make('status')
                                    ->label('Pipeline status')
                                    ->options(ProspectStatus::class)
                                    ->default(fn (Prospect $record): ProspectStatus => $record->status)
                                    ->required(),
                            ])
                            ->action(function (Prospect $record, array $data): void {
                                $status = $data['status'] instanceof ProspectStatus
                                    ? $data['status']
                                    : ProspectStatus::from($data['status']);

                                $record->overrideStatus($status, auth()->id());
                            })
                    ),
                TextColumn::make('form_type')->badge()->color('gray'),
                TextColumn::make('submitted_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    ProspectStatus::PendingReview->value => ProspectStatus::PendingReview->getLabel(),
                    ProspectStatus::DraftReady->value => ProspectStatus::DraftReady->getLabel(),
                    ProspectStatus::Spam->value => ProspectStatus::Spam->getLabel(),
                    ProspectStatus::Unanswerable->value => ProspectStatus::Unanswerable->getLabel(),
                    ProspectStatus::Replied->value => ProspectStatus::Replied->getLabel(),
                ]),
                SelectFilter::make('form_type')->options(fn (): array => Prospect::query()
                    ->whereNotNull('form_type')
                    ->distinct()
                    ->orderBy('form_type')
                    ->pluck('form_type', 'form_type')
                    ->all()),
            ])
            ->persistFiltersInSession()
            ->deferFilters(false)
            ->recordActions([
                ViewAction::make('review')
                    ->label('Review')
                    ->icon(Heroicon::Cog6Tooth)
                    ->modalCancelActionLabel('Close')
                    ->slideOver(),
            ])
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->recordAction('review');
    }

    // Pure descending by submitted_at, no status-based bucketing. A row's
    // position now depends only on when it came in, never on its current
    // status, so reviewing/classifying a prospect can no longer jump it to a
    // different spot in the table -- it stays put. In practice this still
    // surfaces prospects needing review near the top: the newest leads are
    // the ones most likely not yet processed, while older ones have already
    // worked their way to Replied/Spam/Unanswerable.
    private static function applyDefaultTableSort(Builder $query): Builder
    {
        return $query
            ->orderByDesc('prospects.submitted_at')
            ->orderByDesc('prospects.id');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Inquiry')->schema([
                TextEntry::make('name')->placeholder('(not given)'),
                TextEntry::make('email')->placeholder('(not given)'),
                TextEntry::make('phone')->placeholder('(not given)'),
                TextEntry::make('form_type')->placeholder('(not given)'),
                TextEntry::make('submitted_at')->dateTime(),
                TextEntry::make('interests')
                    ->placeholder('(none)')
                    ->formatStateUsing(fn (mixed $state): string => self::formatInterests($state)),
                TextEntry::make('message')
                    ->placeholder('(no message)')
                    ->extraAttributes(['style' => 'white-space: pre-line;'])
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Review')
                ->visible(fn (Prospect $record): bool => $record->status->isActive() || $record->classification !== null || $record->human_classification !== null)
                ->afterHeader([
                    ActionGroup::make([
                        Action::make('markValid')
                            ->label('Valid')
                            ->icon(Heroicon::CheckCircle)
                            ->color('success')
                            ->action(fn (Prospect $record): bool => $record->markStatus(ProspectStatus::Valid, auth()->id())),
                        Action::make('markSpam')
                            ->label('Spam')
                            ->icon(Heroicon::XCircle)
                            ->color('gray')
                            ->action(fn (Prospect $record): bool => $record->markStatus(ProspectStatus::Spam, auth()->id())),
                        Action::make('markUnanswerable')
                            ->label('Unanswerable')
                            ->icon(Heroicon::ExclamationTriangle)
                            ->color('warning')
                            ->action(fn (Prospect $record): bool => $record->markStatus(ProspectStatus::Unanswerable, auth()->id())),
                    ])
                        ->label('Classify')
                        ->icon(Heroicon::ChevronDown)
                        ->button(),
                    DeleteAction::make()
                        ->color(Color::hex('#a3433f'))
                        ->cancelParentActions(),
                ])
                ->schema([
                    TextEntry::make('status')
                        ->badge()
                        ->icon(fn (Prospect $record): Heroicon|HtmlString|null => $record->status->isActive() ? self::spinningStatusIcon() : null)
                        ->columnSpanFull(),
                    TextEntry::make('classification')
                        ->label('AI classification')
                        ->badge()
                        ->placeholder('Not classified yet'),
                    TextEntry::make('classification_confidence')
                        ->label('AI confidence')
                        ->suffix('%')
                        ->placeholder('Not classified yet'),
                    TextEntry::make('classification_reason')
                        ->label('AI reasoning')
                        ->placeholder('No reasoning available')
                        ->columnSpanFull(),
                    TextEntry::make('human_classification')
                        ->label('Human decision')
                        ->badge()
                        ->placeholder('Not reviewed yet'),
                    TextEntry::make('reviewedBy.name')
                        ->label('Reviewed by')
                        ->placeholder('Not reviewed yet'),
                ])
                ->columns(2),

            Section::make('Reply')
                ->visible(fn (Prospect $record): bool => in_array($record->status, [ProspectStatus::DraftReady, ProspectStatus::Replied], true))
                ->schema([
                    Section::make('AI Draft')
                        ->visible(fn (Prospect $record): bool => ! self::hasEditedReply($record))
                        ->description('Generated automatically. Edit your final reply below. Future automatic generation may learn from your edits.')
                        ->afterHeader([
                            Action::make('regenerateDraft')
                                ->label('Regenerate Draft')
                                ->icon(Heroicon::ArrowPath)
                                ->visible(fn (Prospect $record): bool => $record->status === ProspectStatus::DraftReady)
                                ->action(fn (Prospect $record): bool => $record->markStatus(ProspectStatus::Valid, auth()->id())),
                        ])
                        ->schema([
                            TextEntry::make('generated_reply')->hiddenLabel()->extraAttributes(['style' => 'white-space: pre-line;'])->columnSpanFull(),
                            TextEntry::make('generation_reason')
                                ->label(new HtmlString('<em>Why the AI drafted it this way</em>'))
                                ->extraAttributes(['style' => 'font-style: italic;'])
                                ->columnSpanFull(),
                            self::sentReplyAttachmentsEntry(),
                            Actions::make([
                                self::editReplyAction('editDraftReply'),
                                self::approveAndSendAction('approveDraftAndSend'),
                            ])->alignEnd(),
                            TextEntry::make('replied_at')->label('Sent at')->dateTime()->visible(fn (Prospect $record): bool => $record->status === ProspectStatus::Replied),
                        ]),

                    Section::make('Edited Reply')
                        ->visible(fn (Prospect $record): bool => self::hasEditedReply($record))
                        ->schema([
                            TextEntry::make('final_reply')->hiddenLabel()->extraAttributes(['style' => 'white-space: pre-line;'])->columnSpanFull(),
                            self::sentReplyAttachmentsEntry(),
                            TextEntry::make('replied_at')->label('Sent at')->dateTime()->visible(fn (Prospect $record): bool => $record->status === ProspectStatus::Replied),
                            Actions::make([
                                self::editReplyAction('editEditedReply'),
                                self::approveAndSendAction('approveEditedAndSend'),
                            ])->alignEnd(),
                            Section::make('AI Draft')
                                ->collapsible()
                                ->collapsed()
                                ->schema([
                                    TextEntry::make('generated_reply')->hiddenLabel()->extraAttributes(['style' => 'white-space: pre-line;'])->columnSpanFull(),
                                    TextEntry::make('generation_reason')
                                        ->label(new HtmlString('<em>Why the AI drafted it this way</em>'))
                                        ->extraAttributes(['style' => 'font-style: italic;'])
                                        ->columnSpanFull(),
                                ]),
                        ]),
                ]),
        ]);
    }

    public static function formatInterests(mixed $state): string
    {
        if (is_string($state)) {
            $decoded = json_decode($state, true);

            if (! is_array($decoded)) {
                return html_entity_decode($state, ENT_QUOTES | ENT_HTML5);
            }

            $state = $decoded;
        }

        return empty($state) ? '' : implode(', ', $state);
    }

    private static function hasEditedReply(Prospect $record): bool
    {
        return filled($record->final_reply) && $record->final_reply !== $record->generated_reply;
    }

    private static function editReplyAction(string $name): Action
    {
        return Action::make($name)
            ->label('Edit reply')
            ->icon(Heroicon::PencilSquare)
            ->button()
            ->visible(fn (Prospect $record): bool => $record->status === ProspectStatus::DraftReady)
            ->modalHeading('Edit reply')
            ->modalSubmitActionLabel('Save reply')
            ->schema([
                Textarea::make('final_reply')->label('Reply text')->required()->rows(10),
                self::replyAttachmentsSection(),
            ])
            ->fillForm(fn (Prospect $record): array => self::replyFormFill($record))
            ->action(fn (array $data, Prospect $record): bool => self::saveReply($record, $data));
    }

    private static function approveAndSendAction(string $name): Action
    {
        return Action::make($name)
            ->label('Approve & Send')
            ->icon(Heroicon::PaperAirplane)
            ->color('success')
            ->button()
            ->visible(fn (Prospect $record): bool => $record->status === ProspectStatus::DraftReady)
            ->requiresConfirmation()
            ->modalHeading(fn (Prospect $record): string => "Send this reply to {$record->name}?")
            ->modalDescription(function (Prospect $record): string {
                $count = count($record->reply_attachments ?? []);

                return 'The saved reply will be emailed with '.$count.' '.Str::plural('image', $count).'.';
            })
            ->modalSubmitActionLabel('Send')
            ->action(function (Prospect $record, Action $action): void {
                if (blank($record->final_reply)) {
                    $record->update(['final_reply' => $record->generated_reply]);
                }

                try {
                    app(ProspectReplyAttachmentService::class)->prepare(self::replyAttachmentData($record));
                } catch (ProspectReplyAttachmentsRejectedException $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Reply not sent')
                        ->body($exception->getMessage())
                        ->persistent()
                        ->send();

                    $action->halt();
                }

                $record->markStatus(ProspectStatus::Replied, auth()->id());
            });
    }

    private static function replyAttachmentsSection(): Section
    {
        return Section::make('Attach images')
            ->icon(Heroicon::PaperClip)
            ->schema([
                GalleryPhotoCarousel::make('website_images')
                    ->label('Photos from the website')
                    ->stagedUploadsFrom('uploads'),
                FileUpload::make('uploads')
                    ->label('Upload images')
                    ->multiple()
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->disk('local')
                    ->directory(fn (Prospect $record): string => self::uploadDirectory($record))
                    ->visibility('private')
                    ->storeFileNamesIn('upload_names')
                    ->afterStateUpdated(fn (Get $get, Set $set, FileUpload $component) => $set(
                        'website_images',
                        GalleryPhotoCarousel::make('website_images')->syncUploadTokens($get('website_images'), $component->getRawState()),
                    )),
            ]);
    }

    private static function uploadDirectory(Prospect $record): string
    {
        return "prospect-replies/{$record->id}";
    }

    /**
     * @return array{final_reply: string, website_images: list<string>, uploads: list<string>, upload_names: array<string, string>}
     */
    private static function replyFormFill(Prospect $record): array
    {
        $entries = collect($record->reply_attachments ?? []);
        $uploads = $entries->where('disk', 'local');

        return [
            'final_reply' => $record->final_reply ?? $record->generated_reply ?? '',
            'website_images' => $entries
                ->map(fn (array $entry): string => $entry['disk'] === 'local' ? GalleryPhotoCarousel::UPLOAD_TOKEN_PREFIX.$entry['path'] : $entry['path'])
                ->values()
                ->all(),
            'uploads' => $uploads->pluck('path')->values()->all(),
            'upload_names' => $uploads->pluck('name', 'path')->all(),
        ];
    }

    /**
     * @param  array{final_reply: string, website_images?: array<int, mixed>|null, uploads?: array<string>|string|null, upload_names?: array<string, string>|null}  $data
     */
    private static function saveReply(Prospect $record, array $data): bool
    {
        $entries = array_map(
            fn (ProspectReplyAttachmentData $attachment): array => $attachment->toArray(),
            self::orderedAttachments($data['website_images'] ?? [], (array) ($data['uploads'] ?? []), $data['upload_names'] ?? []),
        );

        return $record->update([
            'final_reply' => $data['final_reply'],
            'reply_attachments' => $entries === [] ? null : $entries,
        ]);
    }

    /**
     * Website images and uploads in the order they were staged; any upload missing from the staged
     * order goes last.
     *
     * @param  array<int, mixed>|null  $stagedOrder  the carousel's dehydrated state
     * @param  array<int, mixed>  $uploadPaths
     * @param  array<string, string>|null  $uploadNames
     * @return list<ProspectReplyAttachmentData>
     */
    private static function orderedAttachments(?array $stagedOrder, array $uploadPaths, ?array $uploadNames): array
    {
        $upload = fn (string $path): ProspectReplyAttachmentData => new ProspectReplyAttachmentData(
            disk: 'local',
            path: $path,
            originalName: $uploadNames[$path] ?? basename($path),
        );
        $attachments = [];
        $stagedUploads = [];

        foreach (GalleryPhotoCarousel::make('website_images')->normalizePaths($stagedOrder) as $entry) {
            if (! str_starts_with($entry, GalleryPhotoCarousel::UPLOAD_TOKEN_PREFIX)) {
                $attachments[] = new ProspectReplyAttachmentData(disk: 'public', path: $entry, originalName: basename($entry));

                continue;
            }

            $position = substr($entry, strlen(GalleryPhotoCarousel::UPLOAD_TOKEN_PREFIX));
            $path = ctype_digit($position) ? ($uploadPaths[(int) $position] ?? null) : null;

            if (is_string($path) && $path !== '' && ! isset($stagedUploads[$position])) {
                $attachments[] = $upload($path);
                $stagedUploads[$position] = true;
            }
        }

        foreach ($uploadPaths as $position => $path) {
            if (is_string($path) && $path !== '' && ! isset($stagedUploads[(string) $position])) {
                $attachments[] = $upload($path);
            }
        }

        return $attachments;
    }

    /**
     * @return list<ProspectReplyAttachmentData>
     */
    private static function replyAttachmentData(Prospect $record): array
    {
        return array_map(ProspectReplyAttachmentData::fromArray(...), array_values($record->reply_attachments ?? []));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProspects::route('/'),
        ];
    }

    private static function sentReplyAttachmentsEntry(): ViewEntry
    {
        return ViewEntry::make('sent_reply_attachments')
            ->hiddenLabel()
            ->columnSpanFull()
            ->view('filament.infolists.sent-reply-attachments')
            ->viewData(fn (Prospect $record): array => ['attachments' => self::sentReplyAttachmentViewData($record)])
            ->visible(fn (Prospect $record): bool => in_array($record->status, [ProspectStatus::DraftReady, ProspectStatus::Replied], true) && self::sentReplyAttachmentViewData($record) !== []);
    }

    /**
     * @return list<array{name: string, url: string}>
     */
    private static function sentReplyAttachmentViewData(Prospect $record): array
    {
        $attachments = [];

        foreach (array_values($record->reply_attachments ?? []) as $index => $entry) {
            $attachment = ProspectReplyAttachmentData::fromArray($entry);

            $attachments[] = [
                'name' => $attachment->originalName,
                'url' => $attachment->disk === 'public'
                    ? Storage::disk('public')->url($attachment->path)
                    : route('admin.prospect-reply-attachments.show', [$record, $index]),
            ];
        }

        return $attachments;
    }

    private static function spinningStatusIcon(): HtmlString
    {
        return new HtmlString(<<<'HTML'
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none" aria-hidden="true" style="animation: dwc-status-spin 1s linear infinite;">
                <style>@keyframes dwc-status-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }</style>
                <path d="M10 3a7 7 0 1 1-6.33 4.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none" />
            </svg>
            HTML);
    }
}
