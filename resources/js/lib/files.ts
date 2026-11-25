/** For drop zones: light up only when at least one dragged item is an image. */
export function hasImages(types: readonly string[]): boolean {
    return types.some((type) => type.startsWith('image/'));
}

export function onlyImages(
    files: Iterable<File> | ArrayLike<File> | null | undefined,
): File[] {
    return Array.from(files ?? []).filter((file) =>
        file.type.startsWith('image/'),
    );
}
