import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { FlashToast } from '@/types/ui';

type SessionFlash = {
    success?: string;
    error?: string;
    toast?: FlashToast;
};

function showFlash(flash: SessionFlash | undefined): void {
    if (!flash) {
        return;
    }

    if (flash.toast) {
        toast[flash.toast.type](flash.toast.message);

        return;
    }

    if (flash.success) {
        toast.success(flash.success);

        return;
    }

    if (flash.error) {
        toast.error(flash.error);
    }
}

export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        showFlash((event as CustomEvent).detail?.flash as SessionFlash | undefined);
    });

    router.on('success', (event) => {
        const page = (event as CustomEvent).detail?.page as { props?: { flash?: SessionFlash } } | undefined;
        showFlash(page?.props?.flash);
    });
}
