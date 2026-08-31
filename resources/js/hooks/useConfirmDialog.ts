import { useState, useCallback } from 'react';

interface DialogConfig {
    title: string;
    message: string;
    description?: string;
    icon?: React.ReactNode;
    iconBgClass?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    confirmButtonClass?: string;
    onConfirm: () => void;
}

interface DialogProps {
    show: boolean;
    title: string;
    message: string;
    description?: string;
    icon?: React.ReactNode;
    iconBgClass?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    confirmButtonClass?: string;
    onConfirm: () => void;
    onCancel: () => void;
}

export function useConfirmDialog() {
    const [config, setConfig] = useState<DialogConfig | null>(null);

    const openDialog = useCallback((dialogConfig: DialogConfig) => {
        setConfig(dialogConfig);
    }, []);

    const closeDialog = useCallback(() => {
        setConfig(null);
    }, []);

    const handleConfirm = useCallback(() => {
        config?.onConfirm();
        setConfig(null);
    }, [config]);

    const dialogProps: DialogProps = {
        show: config !== null,
        title: config?.title ?? '',
        message: config?.message ?? '',
        description: config?.description,
        icon: config?.icon,
        iconBgClass: config?.iconBgClass,
        confirmLabel: config?.confirmLabel,
        cancelLabel: config?.cancelLabel,
        confirmButtonClass: config?.confirmButtonClass,
        onConfirm: handleConfirm,
        onCancel: closeDialog,
    };

    return { dialogProps, openDialog, closeDialog };
}
