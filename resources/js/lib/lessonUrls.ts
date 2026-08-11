export type TrustedVideo = {
    provider: 'YouTube' | 'Vimeo';
    embedUrl: string;
};

export function validateLessonLinkUrl(value: string): string | null {
    const normalized = value.trim();

    if (!normalized) {
        return 'URL is required.';
    }

    const parsed = parseUrl(normalized);

    if (!parsed) {
        return 'Enter a complete URL such as https://example.com/resource.';
    }

    if (!['http:', 'https:'].includes(parsed.protocol)) {
        return 'Links must use HTTP or HTTPS.';
    }

    if (parsed.username || parsed.password) {
        return 'Links cannot include embedded usernames or passwords.';
    }

    return null;
}

export function validateLessonVideoUrl(value: string): string | null {
    const normalized = value.trim();

    if (!normalized) {
        return 'Video URL is required.';
    }

    const parsed = parseUrl(normalized);

    if (
        !parsed ||
        !['http:', 'https:'].includes(parsed.protocol) ||
        parsed.username ||
        parsed.password
    ) {
        return 'Enter a complete HTTP or HTTPS URL.';
    }

    return parseTrustedVideo(normalized)
        ? null
        : 'Only YouTube and Vimeo video URLs are supported.';
}

export function parseTrustedVideo(value: string): TrustedVideo | null {
    const parsed = parseUrl(value.trim());

    if (
        !parsed ||
        !['http:', 'https:'].includes(parsed.protocol) ||
        parsed.username ||
        parsed.password
    ) {
        return null;
    }

    const host = parsed.hostname.toLowerCase();
    const segments = parsed.pathname.split('/').filter(Boolean);
    let id: string | null = null;

    if (['youtube.com', 'www.youtube.com', 'm.youtube.com'].includes(host)) {
        id =
            segments[0] === 'embed' || segments[0] === 'shorts'
                ? (segments[1] ?? null)
                : parsed.searchParams.get('v');
    } else if (host === 'youtu.be') {
        id = segments[0] ?? null;
    }

    if (id && /^[A-Za-z0-9_-]{6,20}$/.test(id)) {
        return {
            provider: 'YouTube',
            embedUrl: `https://www.youtube-nocookie.com/embed/${id}`,
        };
    }

    if (['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'].includes(host)) {
        id = segments.at(-1) ?? null;

        if (id && /^\d+$/.test(id)) {
            return {
                provider: 'Vimeo',
                embedUrl: `https://player.vimeo.com/video/${id}`,
            };
        }
    }

    return null;
}

function parseUrl(value: string): URL | null {
    try {
        return new URL(value);
    } catch {
        return null;
    }
}
