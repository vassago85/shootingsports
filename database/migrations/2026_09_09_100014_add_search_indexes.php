<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE INDEX organisations_name_trgm ON organisations USING gin (name gin_trgm_ops)');
        DB::statement('CREATE INDEX venues_name_trgm ON venues USING gin (name gin_trgm_ops)');
        DB::statement('CREATE INDEX events_title_trgm ON events USING gin (title gin_trgm_ops)');
        DB::statement('CREATE INDEX providers_name_trgm ON providers USING gin (name gin_trgm_ops)');

        DB::statement("ALTER TABLE organisations ADD COLUMN IF NOT EXISTS search_vector tsvector GENERATED ALWAYS AS (to_tsvector('english', coalesce(name, '') || ' ' || coalesce(description, ''))) STORED");
        DB::statement('CREATE INDEX organisations_search_vector_idx ON organisations USING gin (search_vector)');

        DB::statement("ALTER TABLE events ADD COLUMN IF NOT EXISTS search_vector tsvector GENERATED ALWAYS AS (to_tsvector('english', coalesce(title, '') || ' ' || coalesce(description, ''))) STORED");
        DB::statement('CREATE INDEX events_search_vector_idx ON events USING gin (search_vector)');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS organisations_name_trgm');
        DB::statement('DROP INDEX IF EXISTS venues_name_trgm');
        DB::statement('DROP INDEX IF EXISTS events_title_trgm');
        DB::statement('DROP INDEX IF EXISTS providers_name_trgm');
        DB::statement('DROP INDEX IF EXISTS organisations_search_vector_idx');
        DB::statement('DROP INDEX IF EXISTS events_search_vector_idx');
        DB::statement('ALTER TABLE organisations DROP COLUMN IF EXISTS search_vector');
        DB::statement('ALTER TABLE events DROP COLUMN IF EXISTS search_vector');
    }
};
