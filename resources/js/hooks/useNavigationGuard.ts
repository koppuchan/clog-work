import { useEffect, useRef, useCallback } from 'react';
import { router } from '@inertiajs/react';

/**
 * 未保存の変更がある場合にページ離脱を防ぐナビゲーションガード
 *
 * - Inertia の Link クリックなどのナビゲーションをインターセプト
 * - ブラウザのタブ閉じ・リロードもガード（beforeunload）
 */
export function useNavigationGuard(
  isDirty: boolean,
  onNavigationBlocked: (proceed: () => void) => void
) {
  const isDirtyRef = useRef(isDirty);
  isDirtyRef.current = isDirty;

  const onNavigationBlockedRef = useRef(onNavigationBlocked);
  onNavigationBlockedRef.current = onNavigationBlocked;

  useEffect(() => {
    // Inertia ナビゲーション（Link クリック等）をインターセプト
    // PUT/POST/PATCH/DELETE（フォーム送信）はガード対象外
    const removeListener = router.on('before', (event) => {
      if (!isDirtyRef.current) return;

      const method = event.detail.visit.method;
      if (method !== 'get') return;

      event.preventDefault();

      onNavigationBlockedRef.current(() => {
        removeListener();
        router.visit(event.detail.visit.url, {
          method: event.detail.visit.method,
        });
      });

      return false;
    });

    return () => {
      removeListener();
    };
  }, []);

  useEffect(() => {
    if (!isDirty) return;

    const handleBeforeUnload = (e: BeforeUnloadEvent) => {
      e.preventDefault();
    };
    window.addEventListener('beforeunload', handleBeforeUnload);

    return () => {
      window.removeEventListener('beforeunload', handleBeforeUnload);
    };
  }, [isDirty]);
}
