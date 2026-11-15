<?php

namespace Tests\Http\Controllers;

use App\Http\Controllers\ShowProspectReplyAttachmentController;
use App\Models\Prospect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ShowProspectReplyAttachmentController::class)]
class ShowProspectReplyAttachmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private function prospectWithAttachment(string $contents = 'image-bytes'): Prospect
    {
        Storage::fake('local');
        Queue::fake();

        $prospect = Prospect::factory()->create();
        $path = "prospect-replies/{$prospect->getKey()}/photo.jpg";
        Storage::disk('local')->put($path, $contents);
        $prospect->update(['reply_attachments' => [['disk' => 'local', 'path' => $path, 'name' => 'photo.jpg']]]);

        return $prospect;
    }

    #[Test]
    public function it_streams_the_attachment_to_an_admin(): void
    {
        $prospect = $this->prospectWithAttachment('image-bytes');

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.prospect-reply-attachments.show', [$prospect, 0]));

        $this->assertSame('image-bytes', $response->streamedContent());
    }

    #[Test]
    public function it_forbids_guests(): void
    {
        $prospect = $this->prospectWithAttachment();

        $this->get(route('admin.prospect-reply-attachments.show', [$prospect, 0]))->assertForbidden();
    }

    #[Test]
    public function it_forbids_users_who_cannot_access_the_panel(): void
    {
        $prospect = $this->prospectWithAttachment();

        $this->actingAs(User::factory()->admin()->inactive()->create())
            ->get(route('admin.prospect-reply-attachments.show', [$prospect, 0]))
            ->assertForbidden();
    }

    #[Test]
    public function it_returns_404_for_an_unlisted_index(): void
    {
        $prospect = $this->prospectWithAttachment();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.prospect-reply-attachments.show', [$prospect, 5]))
            ->assertNotFound();
    }

    #[Test]
    public function it_returns_404_when_the_file_is_missing(): void
    {
        $prospect = $this->prospectWithAttachment();
        Storage::disk('local')->delete($prospect->reply_attachments[0]['path']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.prospect-reply-attachments.show', [$prospect, 0]))
            ->assertNotFound();
    }

    #[Test]
    public function it_returns_404_for_a_path_outside_the_prospect_directory(): void
    {
        $prospect = $this->prospectWithAttachment();
        Storage::disk('local')->put('secret.txt', 'secret');
        $prospect->update(['reply_attachments' => [['disk' => 'local', 'path' => 'secret.txt', 'name' => 'x']]]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.prospect-reply-attachments.show', [$prospect, 0]))
            ->assertNotFound();
    }
}
