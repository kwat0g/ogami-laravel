import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { ArrowLeft, Settings, Pencil, PowerOff, Power, Plus } from 'lucide-react';
import { toast } from 'sonner';
import { useEquipmentDetail, useUpdateEquipment, useStorePmSchedule } from '@/hooks/useMaintenance';
import SkeletonLoader from '@/components/ui/SkeletonLoader';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import type { EquipmentStatus } from '@/types/maintenance';

interface ConfirmState {
  open: boolean;
  title: string;
  description: string;
  confirmLabel: string;
  variant: 'danger' | 'warning';
  onConfirm: () => void;
}

const STATUS_COLOR: Record<string, string> = {
  operational: 'bg-green-50 text-green-700',
  under_maintenance: 'bg-yellow-50 text-yellow-700',
  decommissioned: 'bg-neutral-100 text-neutral-500',
};

const STATUS_LABEL: Record<string, string> = {
  operational: 'Operational',
  under_maintenance: 'Under Maintenance',
  decommissioned: 'Decommissioned',
};

const INPUT = 'w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 bg-white';

export default function EquipmentDetailPage(): React.ReactElement {
  const { ulid } = useParams<{ ulid: string }>();
  const navigate = useNavigate();
  const { data, isLoading, isError } = useEquipmentDetail(ulid ?? '');
  const updateMut = useUpdateEquipment(ulid ?? '');
  const storePmMut = useStorePmSchedule(ulid ?? '');

  const [isEditing, setIsEditing] = useState(false);
  const [showAddPm, setShowAddPm] = useState(false);
  const [pmForm, setPmForm] = useState({ task_name: '', frequency_days: '', last_done_on: '' });
  const [confirm, setConfirm] = useState<ConfirmState>({
    open: false, title: '', description: '', confirmLabel: 'Confirm', variant: 'danger', onConfirm: () => {},
  });
  const closeConfirm = () => setConfirm(s => ({ ...s, open: false }));

  const [form, setForm] = useState({
    name: '',
    category: '',
    manufacturer: '',
    model_number: '',
    serial_number: '',
    location: '',
    commissioned_on: '',
    status: 'operational' as EquipmentStatus,
  });

  if (isLoading) return <SkeletonLoader rows={6} />;

  if (isError || !data?.data) {
    return (
      <div className="text-sm text-red-600 mt-4">
        Equipment not found or you do not have access.{' '}
        <button onClick={() => navigate('/maintenance/equipment')} className="underline text-neutral-600">
          Back to list
        </button>
      </div>
    );
  }

  const eq = data.data;

  const startEdit = () => {
    setForm({
      name: eq.name,
      category: eq.category ?? '',
      manufacturer: eq.manufacturer ?? '',
      model_number: eq.model_number ?? '',
      serial_number: eq.serial_number ?? '',
      location: eq.location ?? '',
      commissioned_on: eq.commissioned_on ?? '',
      status: eq.status,
    });
    setIsEditing(true);
  };

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.name.trim()) { toast.error('Name is required.'); return; }
    try {
      await updateMut.mutateAsync({
        name: form.name,
        category: form.category || undefined,
        manufacturer: form.manufacturer || undefined,
        model_number: form.model_number || undefined,
        serial_number: form.serial_number || undefined,
        location: form.location || undefined,
        commissioned_on: form.commissioned_on || undefined,
        status: form.status,
      });
      toast.success('Equipment updated.');
      setIsEditing(false);
    } catch {
      toast.error('Failed to update equipment.');
    }
  };

  const handleDecommission = () => {
    setConfirm({
      open: true,
      title: 'Decommission equipment?',
      description: 'This marks the equipment as permanently retired. All action buttons will be locked.',
      confirmLabel: 'Decommission',
      variant: 'danger',
      onConfirm: async () => {
        try {
          await updateMut.mutateAsync({ status: 'decommissioned', is_active: false });
          toast.success('Equipment decommissioned.');
        } catch {
          toast.error('Failed to decommission equipment.');
        }
      },
    });
  };

  const handleToggleActive = () => {
    const isActive = eq.is_active;
    setConfirm({
      open: true,
      title: isActive ? 'Deactivate this equipment?' : 'Activate this equipment?',
      description: isActive
        ? 'The equipment will be hidden from active lists until reactivated.'
        : 'The equipment will be made active and visible in all lists.',
      confirmLabel: isActive ? 'Deactivate' : 'Activate',
      variant: isActive ? 'danger' : 'warning',
      onConfirm: async () => {
        try {
          await updateMut.mutateAsync({ is_active: !isActive });
          toast.success(`Equipment ${isActive ? 'deactivated' : 'activated'}.`);
        } catch {
          toast.error('Failed to update equipment.');
        }
      },
    });
  };

  const set = (k: keyof typeof form, v: string) => setForm(prev => ({ ...prev, [k]: v }));

  const handleAddPm = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!pmForm.task_name.trim()) { toast.error('Task name is required.'); return; }
    const freqDays = parseInt(pmForm.frequency_days, 10);
    if (!freqDays || freqDays < 1) { toast.error('Frequency must be at least 1 day.'); return; }
    try {
      await storePmMut.mutateAsync({
        task_name: pmForm.task_name.trim(),
        frequency_days: freqDays,
        ...(pmForm.last_done_on ? { last_done_on: pmForm.last_done_on } : {}),
      });
      toast.success('PM schedule added.');
      setPmForm({ task_name: '', frequency_days: '', last_done_on: '' });
      setShowAddPm(false);
    } catch {
      toast.error('Failed to add PM schedule.');
    }
  };

  const isDecommissioned = eq.status === 'decommissioned';

  return (
    <div className="max-w-3xl">
      {/* Header */}
      <div className="flex items-center justify-between gap-4 mb-6">
        <div className="flex items-center gap-3">
          <button
            type="button"
            onClick={() => navigate('/maintenance/equipment')}
            className="p-2 rounded-lg border border-neutral-200 bg-white hover:bg-neutral-50 hover:border-neutral-300 text-neutral-500"
            aria-label="Back to Equipment"
          >
            <ArrowLeft className="w-4 h-4" />
          </button>
          <div className="w-10 h-10 bg-neutral-100 rounded-lg flex items-center justify-center shrink-0">
            <Settings className="w-5 h-5 text-neutral-600" />
          </div>
          <div>
            <div className="flex items-center gap-3">
              <h1 className="text-lg font-semibold text-neutral-900 font-mono">{eq.equipment_code}</h1>
              <span className={`inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium ${STATUS_COLOR[eq.status] ?? 'bg-neutral-100 text-neutral-600'}`}>
                {STATUS_LABEL[eq.status] ?? eq.status}
              </span>
              {!eq.is_active && (
                <span className="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-red-50 text-red-600">
                  Inactive
                </span>
              )}
            </div>
            <p className="text-sm text-neutral-500 mt-0.5">{eq.name}</p>
          </div>
        </div>

        {/* Actions */}
        {!isEditing && (
          <div className="flex items-center gap-2 shrink-0">
            {!isDecommissioned && (
              <button
                type="button"
                onClick={startEdit}
                className="flex items-center gap-1.5 px-3 py-1.5 text-sm border border-neutral-300 rounded hover:bg-neutral-50"
              >
                <Pencil className="w-3.5 h-3.5" />
                Edit
              </button>
            )}
            {!isDecommissioned && (
              <button
                type="button"
                onClick={handleToggleActive}
                disabled={updateMut.isPending}
                className="flex items-center gap-1.5 px-3 py-1.5 text-sm border border-neutral-300 rounded hover:bg-neutral-50 disabled:opacity-50"
              >
                {eq.is_active ? <PowerOff className="w-3.5 h-3.5" /> : <Power className="w-3.5 h-3.5" />}
                {eq.is_active ? 'Deactivate' : 'Activate'}
              </button>
            )}
            {!isDecommissioned && (
              <button
                type="button"
                onClick={handleDecommission}
                disabled={updateMut.isPending}
                className="flex items-center gap-1.5 px-3 py-1.5 text-sm border border-red-200 text-red-600 rounded hover:bg-red-50 disabled:opacity-50"
              >
                <PowerOff className="w-3.5 h-3.5" />
                Decommission
              </button>
            )}
          </div>
        )}
      </div>

      {/* View mode */}
      {!isEditing && (
        <div className="bg-white border border-neutral-200 rounded p-6 mb-5">
          <dl className="grid grid-cols-2 gap-x-8 gap-y-4 text-sm">
            <div>
              <dt className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Category</dt>
              <dd className="mt-1 text-neutral-900">{eq.category ?? '—'}</dd>
            </div>
            <div>
              <dt className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Manufacturer</dt>
              <dd className="mt-1 text-neutral-900">{eq.manufacturer ?? '—'}</dd>
            </div>
            <div>
              <dt className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Model No.</dt>
              <dd className="mt-1 text-neutral-900">{eq.model_number ?? '—'}</dd>
            </div>
            <div>
              <dt className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Serial No.</dt>
              <dd className="mt-1 font-mono text-neutral-900">{eq.serial_number ?? '—'}</dd>
            </div>
            <div>
              <dt className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Location</dt>
              <dd className="mt-1 text-neutral-900">{eq.location ?? '—'}</dd>
            </div>
            <div>
              <dt className="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Date Commissioned</dt>
              <dd className="mt-1 text-neutral-900">{eq.commissioned_on ?? '—'}</dd>
            </div>
          </dl>
        </div>
      )}

      {/* Edit mode */}
      {isEditing && (
        <form onSubmit={handleSave} className="bg-white border border-neutral-200 rounded p-6 mb-5 space-y-5">
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Name *</label>
            <input type="text" className={INPUT} value={form.name} onChange={e => set('name', e.target.value)} required />
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Category</label>
              <input type="text" className={INPUT} value={form.category} onChange={e => set('category', e.target.value)} />
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Status</label>
              <select className={INPUT} value={form.status} onChange={e => set('status', e.target.value)}>
                <option value="operational">Operational</option>
                <option value="under_maintenance">Under Maintenance</option>
                <option value="decommissioned">Decommissioned</option>
              </select>
            </div>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Manufacturer</label>
              <input type="text" className={INPUT} value={form.manufacturer} onChange={e => set('manufacturer', e.target.value)} />
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Model No.</label>
              <input type="text" className={INPUT} value={form.model_number} onChange={e => set('model_number', e.target.value)} />
            </div>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Serial No.</label>
              <input type="text" className={INPUT} value={form.serial_number} onChange={e => set('serial_number', e.target.value)} />
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Location</label>
              <input type="text" className={INPUT} value={form.location} onChange={e => set('location', e.target.value)} />
            </div>
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Date Commissioned</label>
            <input type="date" className={INPUT} value={form.commissioned_on} onChange={e => set('commissioned_on', e.target.value)} />
          </div>
          <div className="flex justify-end gap-3 pt-1">
            <button
              type="button"
              onClick={() => setIsEditing(false)}
              className="px-4 py-2 text-sm border border-neutral-300 rounded hover:bg-neutral-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={updateMut.isPending}
              className="px-4 py-2 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50"
            >
              {updateMut.isPending ? 'Saving…' : 'Save Changes'}
            </button>
          </div>
        </form>
      )}

      {/* PM Schedules */}
      <div>
        <div className="flex items-center justify-between mb-2">
          <h2 className="text-sm font-semibold text-neutral-700">PM Schedules</h2>
          {!isDecommissioned && (
            <button
              type="button"
              onClick={() => setShowAddPm(s => !s)}
              className="flex items-center gap-1 px-3 py-1.5 text-sm border border-neutral-300 rounded hover:bg-neutral-50"
            >
              <Plus className="w-3.5 h-3.5" />
              Add PM Schedule
            </button>
          )}
        </div>

        {showAddPm && (
          <form onSubmit={handleAddPm} className="mb-3 bg-white border border-neutral-200 rounded p-4 space-y-3">
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Task Name *</label>
              <input
                type="text"
                className={INPUT}
                value={pmForm.task_name}
                onChange={e => setPmForm(s => ({ ...s, task_name: e.target.value }))}
                placeholder="e.g. Lubricate bearings"
                required
              />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">Frequency (days) *</label>
                <input
                  type="number"
                  min={1}
                  className={INPUT}
                  value={pmForm.frequency_days}
                  onChange={e => setPmForm(s => ({ ...s, frequency_days: e.target.value }))}
                  placeholder="30"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-neutral-700 mb-1">Last Done On</label>
                <input
                  type="date"
                  className={INPUT}
                  value={pmForm.last_done_on}
                  onChange={e => setPmForm(s => ({ ...s, last_done_on: e.target.value }))}
                />
              </div>
            </div>
            <div className="flex justify-end gap-2 pt-1">
              <button
                type="button"
                onClick={() => { setShowAddPm(false); setPmForm({ task_name: '', frequency_days: '', last_done_on: '' }); }}
                className="px-3 py-1.5 text-sm border border-neutral-300 rounded hover:bg-neutral-50"
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={storePmMut.isPending}
                className="px-3 py-1.5 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50"
              >
                {storePmMut.isPending ? 'Saving…' : 'Save'}
              </button>
            </div>
          </form>
        )}

        {eq.pm_schedules && eq.pm_schedules.length > 0 ? (
          <div className="overflow-hidden rounded border border-neutral-200 bg-white">
            <table className="w-full text-sm">
              <thead className="bg-neutral-50 text-xs text-neutral-500 uppercase tracking-wide">
                <tr>
                  <th className="px-4 py-3 text-left">Task</th>
                  <th className="px-4 py-3 text-left">Every</th>
                  <th className="px-4 py-3 text-left">Last Done</th>
                  <th className="px-4 py-3 text-left">Next Due</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {eq.pm_schedules.map(s => (
                  <tr key={s.id} className="hover:bg-neutral-50">
                    <td className="px-4 py-3">{s.task_name}</td>
                    <td className="px-4 py-3">{s.frequency_days}d</td>
                    <td className="px-4 py-3">{s.last_done_on ?? '—'}</td>
                    <td className="px-4 py-3">{s.next_due_on ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <p className="text-sm text-neutral-400 py-2">No PM schedules yet.</p>
        )}
      </div>

      {/* Linked work orders hint */}
      {eq.work_orders_count !== undefined && eq.work_orders_count > 0 && (
        <p className="mt-4 text-sm text-neutral-500">
          {eq.work_orders_count} linked work order{eq.work_orders_count !== 1 ? 's' : ''} —{' '}
          <button
            onClick={() => navigate('/maintenance/work-orders')}
            className="underline underline-offset-2 hover:text-neutral-800"
          >
            view in Work Orders
          </button>
        </p>
      )}

      <ConfirmDialog
        open={confirm.open}
        onClose={closeConfirm}
        onConfirm={confirm.onConfirm}
        title={confirm.title}
        description={confirm.description}
        confirmLabel={confirm.confirmLabel}
        variant={confirm.variant}
        loading={updateMut.isPending}
      />
    </div>
  );
}
