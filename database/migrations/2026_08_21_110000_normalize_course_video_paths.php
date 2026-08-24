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
                $urlPath = parse_url($course->video_path, PHP_URL_PATH);

                if (! is_string($urlPath)) {
                    return;
                }

                $publicPath = str_starts_with($urlPath, '/storage/')
                    ? str_replace('/storage/', '', $urlPath)
                    : ltrim($urlPath, '/');

                if (! str_starts_with($publicPath, 'course_videos/')) {
                    return;
                }

                if (! $publicDisk->exists($publicPath) && $privateDisk->exists($publicPath)) {
                    $stream = $privateDisk->readStream($publicPath);

                    try {
                        if ($stream === false || ! $publicDisk->put($publicPath, $stream)) {
                            return;
                        }
                    } finally {
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                    }
                }

                if ($publicDisk->exists($publicPath)) {
                    DB::table('courses')
                        ->where('id', $course->id)
                        ->update(['video_path' => '/storage/'.$publicPath]);
                }
            });
    }

    public function down(): void
    {
        // Public relative paths remain valid when this migration is rolled back.
    }
};
