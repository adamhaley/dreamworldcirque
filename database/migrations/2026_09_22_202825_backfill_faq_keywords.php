<?php

use App\Models\FaqItem;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Gives FAQs that already exist the same chatbot keywords a fresh seed
     * would have created, reading the one shared file rather than repeating
     * the list here. Only fills rows that have none, so an editor's own
     * curation is never overwritten by a later deploy.
     */
    public function up(): void
    {
        $keywords = require database_path('seeders/data/faq-keywords.php');

        FaqItem::query()->whereNull('keywords')->each(function (FaqItem $faq) use ($keywords): void {
            if (isset($keywords[$faq->question])) {
                $faq->update(['keywords' => $keywords[$faq->question]]);
            }
        });
    }

    public function down(): void
    {
        $keywords = require database_path('seeders/data/faq-keywords.php');

        FaqItem::query()->whereIn('keywords', array_values($keywords))->update(['keywords' => null]);
    }
};
