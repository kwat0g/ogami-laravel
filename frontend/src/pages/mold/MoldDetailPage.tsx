import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useMold, useLogShots } from '@/hooks/useMold';
import { useEmployees } from '@/hooks/useEmployees';
import { useProductionOrders } from '@/hooks/useProduction';
import { useForm, Controller } from 'react-hook-form';
import { toast } from 'sonner';
import type { LogShotsPayload } from '@/types/mold';

export default function MoldDetailPage() {
  const { ulid } = useParams<{ ulid: string }>();
  const { data, isLoading } = useMold(ulid ?? '');
  const logShots = useLogShots(ulid ?? '');
  const [showForm, setShowForm] = useState(false);
  const { data: employeesData } = useEmployees({ per_page: 200, is_active: true })
  const employees = employeesData?.data ?? []
  const { data: ordersData } = useProductionOrders({ status: 'in_progress' })
  const productionOrders = ordersData?.data ?? []

  const { register, handleSubmit, reset, control, formState: { isSubmitting } } = useForm<LogShotsPayload>({
    defaultValues: { log_date: new Date().toISOString().split('T')[0] },
  });

  const mold = data?.data;

  if (isLoading) return <div className="py-12 text-center text-neutral-400">Loading…</div>;
  if (!mold) return <div className="py-12 text-center text-neutral-400">Mold not found.</div>;

  const shotPct = mold.max_shots ? Math.min(100, (mold.current_shots / mold.max_shots) * 100) : null;

  const onSubmit = async (values: LogShotsPayload) => {
    try {
      await logShots.mutateAsync(values);
      toast.success('Shot log recorded.');
      reset({ log_date: new Date().toISOString().split('T')[0] });
      setShowForm(false);
    } catch {
      toast.error('Failed to log shots.');
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex items-start justify-between">
        <div>
          <p className="font-mono text-xs text-neutral-400">{mold.mold_code}</p>
          <h1 className="text-lg font-semibold text-neutral-900 mb-6">{mold.name}</h1>
        </div>
        <div className="flex items-center gap-2">
          {mold.is_critical && (
            <span className="rounded bg-neutral-100 px-3 py-1 text-sm font-medium text-neutral-700">Critical</span>
          )}
          <span className="rounded bg-neutral-100 px-3 py-1 text-sm capitalize">{mold.status.replace('_', ' ')}</span>
        </div>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-3 gap-4">
        <div className="rounded-lg border border-neutral-200 bg-white p-4 text-center">
          <p className="text-2xl font-bold">{mold.cavity_count}</p>
          <p className="text-xs text-neutral-500">Cavities</p>
        </div>
        <div className="rounded-lg border border-neutral-200 bg-white p-4 text-center">
          <p className="text-2xl font-bold">{mold.current_shots.toLocaleString()}</p>
          <p className="text-xs text-neutral-500">Total Shots</p>
          {shotPct !== null && (
            <div className="mt-2 h-1.5 overflow-hidden rounded bg-neutral-200">
              <div
                className={`h-full rounded ${shotPct >= 90 ? 'bg-neutral-700' : shotPct >= 70 ? 'bg-neutral-500' : 'bg-neutral-400'}`}
                style={{ width: `${shotPct}%` }}
              />
            </div>
          )}
        </div>
        <div className="rounded-lg border border-neutral-200 bg-white p-4 text-center">
          <p className="text-2xl font-bold">{mold.max_shots?.toLocaleString() ?? '∞'}</p>
          <p className="text-xs text-neutral-500">Max Shots</p>
        </div>
      </div>

      {/* Log Shots */}
      <div>
        <div className="flex items-center justify-between mb-2">
          <h2 className="text-sm font-medium text-neutral-900">Shot Log</h2>
          <button
            onClick={() => setShowForm(s => !s)}
            className="rounded bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-neutral-800"
          >
            + Log Shots
          </button>
        </div>

        {showForm && (
          <form onSubmit={handleSubmit(onSubmit)} className="mb-4 rounded-lg border border-neutral-200 bg-white p-4 space-y-3">
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="text-xs font-medium text-neutral-600">Shot Count *</label>
                <input
                  type="number"
                  min={1}
                  {...register('shot_count', { valueAsNumber: true })}
                  className="mt-1 block w-full rounded border border-neutral-300 px-2 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400"
                />
              </div>
              <div>
                <label className="text-xs font-medium text-neutral-600">Log Date *</label>
                <input
                  type="date"
                  {...register('log_date')}
                  className="mt-1 block w-full rounded border border-neutral-300 px-2 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400"
                />
              </div>
            </div>
            <div>
              <label className="text-xs font-medium text-neutral-600">Remarks</label>
              <input
                type="text"
                {...register('remarks')}
                className="mt-1 block w-full rounded border border-neutral-300 px-2 py-1.5 text-sm focus:ring-1 focus:ring-neutral-400"
              />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="text-xs font-medium text-neutral-600">Operator</label>
                <Controller
                  control={control}
                  name="operator_id"
                  render={({ field }) => (
                    <select
                      {...field}
                      onChange={e => field.onChange(Number(e.target.value) || null)}
                      className="mt-1 block w-full rounded border border-neutral-300 px-2 py-1.5 text-sm bg-white focus:ring-1 focus:ring-neutral-400"
                    >
                      <option value="">— Select Operator —</option>
                      {employees.map(emp => (
                        <option key={emp.id} value={emp.id}>{emp.full_name} ({emp.employee_code})</option>
                      ))}
                    </select>
                  )}
                />
              </div>
              <div>
                <label className="text-xs font-medium text-neutral-600">Production Order (optional)</label>
                <Controller
                  control={control}
                  name="production_order_id"
                  render={({ field }) => (
                    <select
                      {...field}
                      onChange={e => field.onChange(Number(e.target.value) || null)}
                      className="mt-1 block w-full rounded border border-neutral-300 px-2 py-1.5 text-sm bg-white focus:ring-1 focus:ring-neutral-400"
                    >
                      <option value="">— None —</option>
                      {productionOrders.map(po => (
                        <option key={po.id} value={po.id}>{po.po_reference}</option>
                      ))}
                    </select>
                  )}
                />
              </div>
            </div>
            <div className="flex gap-2 justify-end">
              <button type="button" onClick={() => setShowForm(false)} className="rounded px-3 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100">Cancel</button>
              <button type="submit" disabled={isSubmitting} className="rounded bg-neutral-900 px-4 py-1.5 text-sm font-medium text-white hover:bg-neutral-800 disabled:opacity-60">
                {isSubmitting ? 'Saving…' : 'Save'}
              </button>
            </div>
          </form>
        )}

        <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 text-xs text-neutral-500">
              <tr>
                <th className="px-4 py-2 text-left">Date</th>
                <th className="px-4 py-2 text-right">Shots</th>
                <th className="px-4 py-2 text-left">Operator</th>
                <th className="px-4 py-2 text-left">Remarks</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {(mold.shot_logs ?? []).length === 0 ? (
                <tr><td colSpan={4} className="px-4 py-6 text-center text-neutral-400">No shot logs yet.</td></tr>
              ) : (
                (mold.shot_logs ?? []).map(log => (
                  <tr key={log.id} className="even:bg-neutral-100 hover:bg-neutral-50">
                    <td className="px-4 py-2 text-neutral-500">{log.log_date}</td>
                    <td className="px-4 py-2 text-right font-medium">{log.shot_count.toLocaleString()}</td>
                    <td className="px-4 py-2 text-neutral-500">{log.operator?.name ?? '—'}</td>
                    <td className="px-4 py-2 text-neutral-500">{log.remarks ?? '—'}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
