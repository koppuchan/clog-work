import { User } from '@/types';

/**
 * 退職日の翌月以降は非表示にする
 */
export function getActiveUsersForMonth(users: User[], selectedDate: Date): User[] {
  return users.filter(user => {
    if (!user.retirementDate) return true;

    const retirementDate = new Date(user.retirementDate);
    const retirementNextMonth = new Date(retirementDate.getFullYear(), retirementDate.getMonth() + 1, 1);

    return selectedDate < retirementNextMonth;
  });
}
