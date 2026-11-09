<?php

use App\Models\MagazineIssue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_magazine_issues', function (Blueprint $table): void {
            $table->unsignedInteger('last_page')->default(1);
        });

        Schema::table('magazine_issues', function (Blueprint $table): void {
            $table->json('page_images')->nullable();
        });

        MagazineIssue::query()->orderBy('id')->each(function (MagazineIssue $issue): void {
            $number = $issue->issue_number;

            if ($number === null && preg_match('/^issue-(\d+)$/', (string) $issue->slug, $matches) === 1) {
                $number = (int) $matches[1];
            }

            $publication = trim((string) $issue->publication);

            if ($publication === '' || $number === null) {
                return;
            }

            $next = Str::slug($publication.'-'.$number);

            if ($next === '' || $next === $issue->slug) {
                return;
            }

            $taken = MagazineIssue::query()
                ->where('slug', $next)
                ->whereKeyNot($issue->id)
                ->exists();

            if ($taken) {
                return;
            }

            $issue->slug = $next;
            $issue->save();
        });
    }

    public function down(): void
    {
        Schema::table('magazine_issues', function (Blueprint $table): void {
            $table->dropColumn('page_images');
        });

        Schema::table('saved_magazine_issues', function (Blueprint $table): void {
            $table->dropColumn('last_page');
        });
    }
};
