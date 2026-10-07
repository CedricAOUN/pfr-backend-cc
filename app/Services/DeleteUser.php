<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DeleteUser
{
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            // Collect files before foreign-key cascades remove their references.
            $this->deleteMedia($user->getRawOriginal('avatar_url'), 'user_avatars');
            foreach (Recipe::where('creator_id', $user->id)->pluck('image_url') as $image) {
                $this->deleteMedia($image, 'recipe_images');
            }
            foreach (Course::where('expert_id', $user->id)->pluck('video_path') as $video) {
                $this->deleteMedia($video, 'course_videos', true);
            }

            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            // Spatie removes its polymorphic role/permission assignments on delete.
            $user->delete();
        });
    }

    private function deleteMedia(?string $value, string $directory, bool $legacyVideo = false): void
    {
        if (! $value) {
            return;
        }

        // External avatars and placeholder images are not application uploads.
        $host = parse_url($value, PHP_URL_HOST);
        if ($host && $host !== parse_url(config('filesystems.disks.public.url'), PHP_URL_HOST)) {
            return;
        }
        $path = parse_url($value, PHP_URL_PATH);
        if (! is_string($path)) {
            return;
        }
        $prefix = rtrim((string) parse_url(config('filesystems.disks.public.url'), PHP_URL_PATH), '/').'/';
        if (str_starts_with($path, $prefix)) {
            $path = substr($path, strlen($prefix));
        }
        if (! str_starts_with($path, $directory.'/') || preg_match('~(?:^|/)[.]{1,2}(?:/|$)|\\\\~', $path)) {
            return;
        }

        foreach ($legacyVideo ? ['public', 'local'] : ['public'] as $diskName) {
            $disk = Storage::disk($diskName);
            if ($disk->exists($path) && ! $disk->delete($path)) {
                throw new RuntimeException('Unable to delete user media.');
            }
        }
    }
}
