import api from './api';

export interface RecentReferral {
  id: string;
  name: string | null;
  status: 'registered' | 'qualified' | 'rewarded';
  joined_at: string | null;
  qualified_at: string | null;
  rewarded_at: string | null;
}

export interface ReferralSummary {
  referral_code: string;
  total_referrals: number;
  qualified_referrals: number;
  rewarded_referrals: number;
  recent_referrals: RecentReferral[];
}

export async function getReferralSummary(): Promise<ReferralSummary> {
  const { data } = await api.get<{ data: ReferralSummary }>(
    '/referrals/summary'
  );

  return data.data;
}
