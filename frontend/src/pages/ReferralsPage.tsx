import { useEffect, useMemo, useState } from 'react';
import {
  Check,
  Copy,
  Gift,
  Share2,
  UserCheck,
  Users,
} from 'lucide-react';
import {
  getReferralSummary,
  type ReferralSummary,
} from '../services/referrals';

export default function ReferralsPage() {
  const [summary, setSummary] = useState<ReferralSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [copied, setCopied] = useState<'code' | 'link' | null>(null);

  useEffect(() => {
    let active = true;

    getReferralSummary()
      .then((result) => {
        if (active) {
          setSummary(result);
        }
      })
      .catch(() => {
        if (active) {
          setError('Unable to load your referral information.');
        }
      })
      .finally(() => {
        if (active) {
          setLoading(false);
        }
      });

    return () => {
      active = false;
    };
  }, []);

  const referralLink = useMemo(() => {
    if (!summary?.referral_code) {
      return '';
    }

    return `${window.location.origin}/register?ref=${encodeURIComponent(
      summary.referral_code
    )}`;
  }, [summary?.referral_code]);

  const copyText = async (
    value: string,
    type: 'code' | 'link'
  ) => {
    try {
      await navigator.clipboard.writeText(value);
      setCopied(type);

      window.setTimeout(() => {
        setCopied(null);
      }, 2000);
    } catch {
      setError('Unable to copy. Please copy it manually.');
    }
  };

  const shareReferral = async () => {
    if (!summary || !referralLink) {
      return;
    }

    const text =
      `Join me on CNMG STOCKS and manage your business more easily. ` +
      `Use my referral code ${summary.referral_code}.`;

    if (navigator.share) {
      try {
        await navigator.share({
          title: 'Join CNMG STOCKS',
          text,
          url: referralLink,
        });

        return;
      } catch (err) {
        if ((err as Error).name === 'AbortError') {
          return;
        }
      }
    }

    await copyText(referralLink, 'link');
  };

  if (loading) {
    return (
      <div className="flex min-h-[50vh] items-center justify-center text-sm text-slate-500">
        Loading your referrals…
      </div>
    );
  }

  if (!summary) {
    return (
      <div className="mx-auto max-w-3xl">
        <div className="rounded-2xl border border-red-100 bg-red-50 p-5 text-sm text-red-700">
          {error || 'Referral information is unavailable.'}
        </div>
      </div>
    );
  }

  const stats = [
    {
      label: 'Total referrals',
      value: summary.total_referrals,
      icon: Users,
    },
    {
      label: 'Qualified',
      value: summary.qualified_referrals,
      icon: UserCheck,
    },
    {
      label: 'Rewarded',
      value: summary.rewarded_referrals,
      icon: Gift,
    },
  ];

  return (
    <div className="mx-auto max-w-4xl space-y-5">
      <div>
        <h1 className="text-2xl font-bold text-slate-900">
          Refer & Earn
        </h1>
        <p className="mt-1 text-sm text-slate-500">
          Invite other businesses to CNMG STOCKS and track your referrals.
        </p>
      </div>

      {error && (
        <div className="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">
          {error}
        </div>
      )}

      <section className="overflow-hidden rounded-2xl bg-teal-800 p-5 text-white shadow-sm">
        <div className="text-sm text-teal-100">
          Your referral code
        </div>

        <div className="mt-2 flex flex-wrap items-center gap-3">
          <div className="text-2xl font-bold tracking-wider">
            {summary.referral_code}
          </div>

          <button
            type="button"
            onClick={() =>
              copyText(summary.referral_code, 'code')
            }
            className="flex items-center gap-1.5 rounded-lg bg-white/15 px-3 py-2 text-xs font-semibold hover:bg-white/20"
          >
            {copied === 'code' ? (
              <Check className="h-4 w-4" />
            ) : (
              <Copy className="h-4 w-4" />
            )}
            {copied === 'code' ? 'Copied' : 'Copy code'}
          </button>
        </div>

        <div className="mt-5 rounded-xl bg-white/10 p-3">
          <div className="break-all text-xs text-teal-50">
            {referralLink}
          </div>
        </div>

        <div className="mt-4 grid grid-cols-2 gap-3">
          <button
            type="button"
            onClick={() => copyText(referralLink, 'link')}
            className="flex items-center justify-center gap-2 rounded-xl bg-white px-3 py-3 text-sm font-semibold text-teal-800"
          >
            {copied === 'link' ? (
              <Check className="h-4 w-4" />
            ) : (
              <Copy className="h-4 w-4" />
            )}
            {copied === 'link' ? 'Copied' : 'Copy link'}
          </button>

          <button
            type="button"
            onClick={shareReferral}
            className="flex items-center justify-center gap-2 rounded-xl bg-teal-600 px-3 py-3 text-sm font-semibold text-white hover:bg-teal-500"
          >
            <Share2 className="h-4 w-4" />
            Share
          </button>
        </div>
      </section>

      <section className="grid grid-cols-3 gap-3">
        {stats.map((stat) => (
          <div
            key={stat.label}
            className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
          >
            <stat.icon className="mb-3 h-5 w-5 text-teal-700" />
            <div className="text-xl font-bold text-slate-900">
              {stat.value}
            </div>
            <div className="mt-1 text-xs text-slate-500">
              {stat.label}
            </div>
          </div>
        ))}
      </section>

      <section className="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div className="border-b border-slate-100 px-5 py-4">
          <h2 className="font-semibold text-slate-900">
            Recent referrals
          </h2>
        </div>

        {summary.recent_referrals.length === 0 ? (
          <div className="px-5 py-10 text-center">
            <Users className="mx-auto h-8 w-8 text-slate-300" />
            <p className="mt-3 text-sm font-medium text-slate-700">
              No referrals yet
            </p>
            <p className="mt-1 text-xs text-slate-500">
              Share your referral link to start inviting businesses.
            </p>
          </div>
        ) : (
          <div className="divide-y divide-slate-100">
            {summary.recent_referrals.map((referral) => (
              <div
                key={referral.id}
                className="flex items-center justify-between gap-3 px-5 py-4"
              >
                <div>
                  <div className="text-sm font-medium text-slate-900">
                    {referral.name || 'CNMG STOCKS user'}
                  </div>
                  <div className="mt-0.5 text-xs text-slate-500">
                    {referral.joined_at
                      ? new Date(
                          referral.joined_at
                        ).toLocaleDateString()
                      : 'Recently joined'}
                  </div>
                </div>

                <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium capitalize text-slate-600">
                  {referral.status}
                </span>
              </div>
            ))}
          </div>
        )}
      </section>

      <section className="rounded-2xl border border-slate-200 bg-white p-5">
        <h2 className="font-semibold text-slate-900">
          How it works
        </h2>

        <p className="mt-2 text-sm leading-6 text-slate-600">
          Share your unique CNMG STOCKS referral link. When someone
          creates their business account using your code, the referral
          is recorded automatically. Qualified and rewarded referrals
          will appear here as the referral programme develops.
        </p>
      </section>
    </div>
  );
}
