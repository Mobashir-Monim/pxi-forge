import { ref } from 'vue';

const open = ref(false);

/** One Buy credits dialog for the whole app, opened from the sidebar or from the prompt bar when credits run short. */
export function useBuyCredits() {
    return { open, show: () => (open.value = true) };
}
