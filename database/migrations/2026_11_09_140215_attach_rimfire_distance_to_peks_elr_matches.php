<?php

use App\Enums\DisciplineFamily;
use App\Models\Discipline;
use App\Models\Event;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $elr = Discipline::query()->where('slug', 'elr')->first();

        if ($elr === null) {
            return;
        }

        $rimfire = Discipline::query()->updateOrCreate(
            ['slug' => '22lr-elr'],
            [
                'name' => '.22 LR ELR',
                'family' => DisciplineFamily::Rifle,
                'parent_id' => $elr->id,
                'short_blurb' => '.22 LR extreme long range.',
                'typical_distances' => '50–750 m',
                'is_published' => false,
                'sort_order' => $elr->sort_order + 1,
            ],
        );

        Event::query()
            ->where('slug', 'like', 'send-it-peks-22lr-elr-%')
            ->get()
            ->each(fn (Event $event) => $event->attachDiscipline($rimfire, false));
    }

    public function down(): void
    {
        //
    }
};
