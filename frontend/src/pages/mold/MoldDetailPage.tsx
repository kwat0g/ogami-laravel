import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { Pencil, Box } from 'lucide-react';
import { useMold, useLogShots, useUpdateMold } from '@/hooks/useMold';
import { useEmployees } from '@/hooks/useEmployees';
import { useProductionOrders } from '@/hooks/useProduction';
import { useForm, Controller } from 'react-hook-form';
import { toast } from 'sonner';
import PageHeader from '@/components/ui/PageHeader';
import StatusBadge from '@/components/ui/StatusBadge';
import { Card, CardHeader, CardBody } from '@/components/ui/Card';
import { InfoRow, InfoList } from '@/components/ui/InfoRow';
import type { LogShotsPayload, MoldStatus } from '@/types/mold';

const INPUT = 'w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 bg-white';

export default function MoldDetailPage() {
  const { ulid } = useParams<{ ulid: string }>();
  const navigate = useNavigate();
  const { data, isLoading } = useMold(ulid ?? '');
  const logShots = useLogShots(ulid ?? '');
  const updateMut = useUpdateMold(ulid ?? '');
  const [showForm, setShowForm] = useState(false);
  const [isEditing, setIsEditing] = useState(false);
  const [editForm, setEditForm] = useState({
    name: '', description: '', cavity_count: '', material: '', location: '', max_shots: '', status: 'active' as MoldStatus,
  });
  const { data: employeesData } = useEmployees({ per_page: 200, is_active: true })
  const employees = (employeesData?.data ?? []).filter(
    (e) => e.department?.name === 'Production' || e.department?.name === 'Mold Department'
  )
  const { data: ordersData } = useProductionOrders({ status: 'in_progress' })
  const productionOrders = ordersData?.data ?? []

  const { register, handleSubmit, reset, control, formState: { isSubmitting } } = useForm<LogShotsPayload>({
    defaultValues: { log_date: new Date().toISOString().split('T')[0] },
  });

  const mold = data?.data;

  if (isLoading) return <div className="py-12 text-center text-neutral-400">Loading…</div>;
  if (!mold) return <div className="py-12 text-center text-neutral-400">Mold not found.</div>;

  const startEdit = () => {
    setEditForm({
      name: mold.name,
      description: mold.description ?? '',
      cavity_count: String(mold.cavity_count),
      material: mold.material ?? '',
      location: mold.location ?? '',
      max_shots: mold.max_shots != null ? String(mold.max_shots) : '',
      status: mold.status,
    });
    setIsEditing(true);
  };

  const handleEditSave = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editForm.name.trim()) { toast.error('Name is required.'); return; }
    const cavityCount = parseInt(editForm.cavity_count, 10);
    if (!cavityCount || cavityCount < 1) { toast.error('Cavity count must be at least 1.'); return; }
    try {
      await updateMut.mutateAsync({
        name: editForm.name.trim(),
        description: editForm.description || undefined,
        cavity_count: cavityCount,
        material: editForm.material || undefined,
        location: editForm.location || undefined,
        max_shots: editForm.max_shots ? parseInt(editForm.max_shots, 10) : undefined,
        status: editForm.status,
      });
      toast.success('Mold updated.');
      setIsEditing(false);
    } catch {
      toast.error('Failed to update mold.');
    }
  };

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

  const statusBadges = (
    <div className="flex items-center gap-2">
      {mold.is_critical && (
        <span className="rounded bg-neutral-100 px-3 py-1 text-sm font-medium text-neutral-700">Critical</span>
      )}
      <StatusBadge label={mold.status} />
    </div>
  );

  return (
    <div className="max-w-5xl mx-auto space-y-6">
      <PageHeader
        backTo="/mold/masters"
        title={mold.name}
        subtitle={mold.mold_code}
        icon={<Box className="w-5 h-5" />}
        status={statusBadges}
        actions={
          !isEditing && (
            <button
              type="button"
              onClick={startEdit}
              className="flex items-center gap-1.5 px-3 py-1.5 text-sm bg-white text-neutral-700 border border-neutral-300 rounded hover:bg-neutral-50"
            >
              <Pencil className="w-3.5 h-3.5" /> Edit
            </button>
          )
        }
      />

      {/* Edit form */}
      {isEditing && (
        <form onSubmit={handleEditSave} className="bg-white border border-neutral-200 rounded p-5 space-y-4">
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Name *</label>
            <input type="text" className={INPUT} value={editForm.name} onChange={e => setEditForm(s => ({ ...s, name: e.target.value }))} required />
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Description</label>
            <textarea className={INPUT} rows={2} value={editForm.description} onChange={e => setEditForm(s => ({ ...s, description: e.target.value }))} />
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Cavity Count *</label>
              <input type="number" min={1} className={INPUT} value={editForm.cavity_count} onChange={e => setEditForm(s => ({ ...s, cavity_count: e.target.value }))} required />
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Max Shots</label>
              <input type="number" min={1} className={INPUT} value={editForm.max_shots} onChange={e => setEditForm(s => ({ ...s, max_shots: e.target.value }))} />
            </div>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Material</label>
              <input type="text" className={INPUT} value={editForm.material} onChange={e => setEditForm(s => ({ ...s, material: e.target.value }))} />
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Location</label>
              <input type="text" className={INPUT} value={editForm.location} onChange={e => setEditForm(s => ({ ...s, location: e.target.value }))} />
            </div>
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Status *</label>
            <select className={INPUT} value={editForm.status} onChange={e => setEditForm(s => ({ ...s, status: e.target.value as MoldStatus }))}>
              <option value="active">Active</option>
              <option value="under_maintenance">Under Maintenance</option>
              <option value="retired">Retired</option>
            </select>
          </div>
          <div className="flex justify-end gap-3 pt-1">
            <button type="button" onClick={() => setIsEditing(false)} className="px-4 py-2 text-sm bg-white text-neutral-700 border border-neutral-300 rounded hover:bg-neutral-50">Cancel</button>
            <button type="submit" disabled={updateMut.isPending} className="px-4 py-2 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50">
              {updateMut.isPending ? 'Saving…' : 'Save Changes'}
            </button>
          </div>
        </form>
      )}

      {/* Stats */}
      <div className="grid grid-cols-3 gap-4">
        <Card>
          <CardBody className="text-center">
            <p className="text-2xl font-bold">{mold.cavity_count}</p>
            <p className="text-xs text-neutral-500">Cavities</p>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="text-center">
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
          </CardBody>
        </Card>
        <Card>
          <CardBody className="text-center">
            <p className="text-2xl font-bold">{mold.max_shots?.toLocaleString() ?? '∞'}</p>
            <p className="text-xs text-neutral-500">Max Shots</p>
          </CardBody>
        </Card>
      </div>

      {/* Log Shots */}
      <Card>
        <CardHeader
          actions={
            <button
              onClick={() => setShowForm(s => !s)}
              className="rounded bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-neutral-800"
            >
              + Log Shots
            </button>
          }
        >
          Shot Log
        </CardHeader>
        <CardBody>
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
                          <option key={emp.id} value={emp.user_id ?? ''}>{emp.full_name} ({emp.position?.title ?? emp.department?.name ?? emp.employee_code})</option>
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
        </CardBody>
      </Card>
    </div>
  );
}
