import { create } from 'zustand';
import { v4 as uuidv4 } from 'uuid';
import type { OfflineOperation, OfflineOperationType } from '../types';
import api from '../services/api';

const STORAGE_KEY = 'mystocks_offline_queue';

function loadQueue(): OfflineOperation[] {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    return raw ? JSON.parse(raw) : [];
  } catch {
    return [];
  }
}

function saveQueue(ops: OfflineOperation[]) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(ops));
}

function currentUserId(): string | null {
  try {
    const storedUser = localStorage.getItem('mystocks_user');
    return storedUser ? JSON.parse(storedUser)?.id ?? null : null;
  } catch {
    return null;
  }
}

interface OfflineState {
  queue: OfflineOperation[];
  isOnline: boolean;
  isSyncing: boolean;
  setOnline: (online: boolean) => void;
  enqueue: (type: OfflineOperationType, payload: Record<string, unknown>) => OfflineOperation;
  markSynced: (idempotencyKey: string) => void;
  markConflict: (idempotencyKey: string, reason: string) => void;
  syncAll: () => Promise<void>;
  pendingCount: () => number;
}

export const useOfflineStore = create<OfflineState>((set, get) => ({
  queue: loadQueue(),
  isOnline: typeof navigator !== 'undefined' ? navigator.onLine : true,
  isSyncing: false,

  setOnline: (online) => set({ isOnline: online }),

  enqueue: (type, payload) => {
    const businessId = localStorage.getItem('mystocks_business_id');
    const userId = currentUserId();

    if (!businessId || !userId) {
      throw new Error('Cannot save an offline operation without an active business and user.');
    }

    const op: OfflineOperation = {
      id: uuidv4(),
      idempotency_key: uuidv4(),
      operation_type: type,
      origin_business_id: businessId,
      origin_user_id: userId,
      payload,
      client_created_at: new Date().toISOString(),
      status: 'pending',
    };
    const queue = [...get().queue, op];
    saveQueue(queue);
    set({ queue });
    return op;
  },

  markSynced: (key) => {
    const queue = get().queue.map((op) =>
      op.idempotency_key === key ? { ...op, status: 'synced' as const } : op
    );
    // Keep only non-synced for next time
    const remaining = queue.filter((op) => op.status !== 'synced');
    saveQueue(remaining);
    set({ queue: remaining });
  },

  markConflict: (key, reason) => {
    const queue = get().queue.map((op) =>
      op.idempotency_key === key
        ? { ...op, status: 'conflict' as const, conflict_reason: reason }
        : op
    );
    saveQueue(queue);
    set({ queue });
  },

  pendingCount: () => get().queue.filter((op) => op.status === 'pending' || op.status === 'failed').length,

  syncAll: async () => {
    const { queue, isOnline, isSyncing } = get();
    if (!isOnline || isSyncing) return;

    const businessId = localStorage.getItem('mystocks_business_id');
    const userId = currentUserId();
    const eligible = (op: OfflineOperation) =>
      op.origin_business_id === businessId && op.origin_user_id === userId;
    const pending = queue.filter(
      (op) => (op.status === 'pending' || op.status === 'failed') && eligible(op)
    );
    const mismatched = queue.map((op) =>
      (op.status === 'pending' || op.status === 'failed') && !eligible(op)
        ? { ...op, status: 'conflict' as const, conflict_reason: 'Operation belongs to a different business or user.' }
        : op
    );

    if (mismatched.some((op, index) => op !== queue[index])) {
      saveQueue(mismatched);
      set({ queue: mismatched });
    }

    if (pending.length === 0) return;

    set({ isSyncing: true });

    try {
      const deviceId = localStorage.getItem('mystocks_device_id') || uuidv4();
      localStorage.setItem('mystocks_device_id', deviceId);

      const { data } = await api.post('/sync/push', {
        device_id: deviceId,
        operations: pending.map((op) => ({
          idempotency_key: op.idempotency_key,
          operation_type: op.operation_type,
          origin_business_id: op.origin_business_id,
          origin_user_id: op.origin_user_id,
          payload: op.payload,
          client_created_at: op.client_created_at,
        })),
      });

      for (const result of data.results || []) {
        if (result.status === 'synced' || result.status === 'already_synced') {
          get().markSynced(result.idempotency_key);
        } else if (result.status === 'conflict') {
          get().markConflict(result.idempotency_key, result.conflict_reason || result.message);
        }
      }
    } catch {
      // Keep pending for next retry
    } finally {
      set({ isSyncing: false });
    }
  },
}));
