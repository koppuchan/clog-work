import { useState, useEffect } from 'react';

/**
 * 1秒ごとに現在時刻を更新するフック
 */
export function useCurrentTime(): Date {
  const [currentTime, setCurrentTime] = useState(new Date());

  useEffect(() => {
    const interval = setInterval(() => {
      setCurrentTime(new Date());
    }, 1000);
    return () => clearInterval(interval);
  }, []);

  return currentTime;
}
