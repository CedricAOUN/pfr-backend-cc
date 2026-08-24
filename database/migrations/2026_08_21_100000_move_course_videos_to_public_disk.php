<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $privateDisk = Storage::disk('local');
        $publicDisk = Storage::disk('public');

        DB::table('courses')
            ->whereNotNull('video_path')
            ->orderBy('id')
            ->each(function (object $course) use ($privateDisk, $publicDisk): void {
                if (str_starts_with($course->video_path, 'http') || str_starts_with($course->video_path, '/storage/')) {
                    return;
                }

                if (! $privateDisk->exists($course->video_path)) {
                    return;
                }

                $stream = $privateDisk->readStream($course->video_path);

                try {
                    if ($stream === false || ! $publicDisk->put($course->video_path, $stream)) {
                        return;
                    }
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }

                DB::table('courses')
                    ->where('id', $course->id)
                    ->update(['video_path' => '/storage/'.$course->video_path]);
            });
    }

    public function down(): void
    {
        $privateDisk = Storage::disk('local');

        DB::table('courses')
            ->whereNotNull('video_path')
            ->orderBy('id')
            ->each(function (object $course) use ($privateDisk): void {
                $path = parse_url($course->video_path, PHP_URL_PATH);

                if (! is_string($path) || ! str_starts_with($path, '/storage/')) {
                    return;
                }

                $privatePath = str_replace('/storage/', '', $path);

                if ($privateDisk->exists($privatePath)) {
                    DB::table('courses')
                        ->where('id', $course->id)
                        ->update(['video_path' => $privatePath]);
                }
            });
    }
};
