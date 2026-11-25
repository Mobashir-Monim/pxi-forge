import { usePage } from '@inertiajs/vue3';

/**
 * Whether the signed-in user holds a permission. Only hides what they can't use: the server checks every request.
 */
export function useCan(): (permission: string) => boolean {
    const page = usePage();

    return (permission) => page.props.auth.permissions.includes(permission);
}
