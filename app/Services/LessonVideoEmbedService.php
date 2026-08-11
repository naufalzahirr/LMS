<?php

namespace App\Services;

final class LessonVideoEmbedService
{
    /**
     * @return array{provider: 'youtube'|'vimeo', video_id: string, embed_url: string}|null
     */
    public function parse(string $url): ?array
    {
        if (! $this->isSafeHttpUrl($url)) {
            return null;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            $id = match (true) {
                str_starts_with($path, 'embed/') => explode('/', $path)[1] ?? null,
                str_starts_with($path, 'shorts/') => explode('/', $path)[1] ?? null,
                default => $query['v'] ?? null,
            };

            return is_string($id) ? $this->youtube($id) : null;
        }

        if ($host === 'youtu.be') {
            return $this->youtube(explode('/', $path)[0]);
        }

        if (in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)) {
            $segments = array_values(array_filter(explode('/', $path)));
            $id = end($segments);

            if (is_string($id) && ctype_digit($id)) {
                return [
                    'provider' => 'vimeo',
                    'video_id' => $id,
                    'embed_url' => "https://player.vimeo.com/video/{$id}",
                ];
            }
        }

        return null;
    }

    public function isSafeHttpUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = (string) parse_url($url, PHP_URL_HOST);
        $user = parse_url($url, PHP_URL_USER);
        $password = parse_url($url, PHP_URL_PASS);

        return in_array($scheme, ['http', 'https'], true)
            && $host !== ''
            && $user === null
            && $password === null;
    }

    /** @return array{provider: 'youtube', video_id: string, embed_url: string}|null */
    private function youtube(string $id): ?array
    {
        if (preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id) !== 1) {
            return null;
        }

        return [
            'provider' => 'youtube',
            'video_id' => $id,
            'embed_url' => "https://www.youtube-nocookie.com/embed/{$id}",
        ];
    }
}
