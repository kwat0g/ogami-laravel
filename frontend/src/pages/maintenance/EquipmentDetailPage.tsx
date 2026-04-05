import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { Settings, Pencil, PowerOff, Power, Plus, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { useEquipmentDetail, useUpdateEquipment, useStorePmSchedule, useDeleteEquipment } from '@/hooks/useMaintenance';
import { useAuthStore } from '@/stores/authStore';
import { PERMISSIONS } from '@/lib/permissions'
import SkeletonLoader from '@/components/ui/SkeletonLoader';
import ConfirmDialog from '@/components/ui/ConfirmDialog';
import ConfirmDestructiveDialog from '@/components/ui/ConfirmDestructiveDialog';
import PageHeader from '@/components/ui/PageHeader';
import StatusBadge from '@/components/ui/StatusBadge';
import { Card, CardHeader, CardBody } from '@/components/ui/Card';
import { InfoRow, InfoList } from '@/components/ui/InfoRow';
import { firstErrorMessage } from '@/lib/errorHandler';
import type { EquipmentStatus } from '@/types/maintenance';

const _STATUS_LABEL: Record<string, string> = {
  operational: 'Operational',
  under_maintenance: 'Under Maintenance',
  decommissioned: 'Decommissioned',
};

const INPUT = 'w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 bg-white';

interface ValidationErrors {
  name?: string;
  category?: string;
  manufacturer?: string;
  model_number?: string;
  serial_number?: string;
  location?: string;
}

export default function EquipmentDetailPage(): React.ReactElement {
  const { ulid } = useParams<{ ulid: string }>();
  const navigate = useNavigate();
  const { hasPermission } = useAuthStore();
  const { data, isLoading, isError } = useEquipmentDetail(ulid ?? '');
  const updateMut = useUpdateEquipment(ulid ?? '');
  const storePmMut = useStorePmSchedule(ulid ?? '');
  const deleteMut = useDeleteEquipment();

  const canManage = hasPermission(PERMISSIONS.maintenance.manage);

  const [isEditing, setIsEditing] = useState(false);
  const [showAddPm, setShowAddPm] = useState(false);
  const [pmForm, setPmForm] = useState({ task_name: '', frequency_days: '', last_done_on: '' });
  const [editErrors, setEditErrors] = useState<ValidationErrors>({});

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

  const validateEditForm = (): boolean => {
    const errors: ValidationErrors = {};
    if (!form.name.trim()) errors.name = 'Name is required.';
    else if (form.name.trim().length < 2) errors.name = 'Name must be at least 2 characters.';
    if (form.category && form.category.length > 100) errors.category = 'Category must be less than 100 characters.';
    if (form.manufacturer && form.manufacturer.length > 100) errors.manufacturer = 'Manufacturer must be less than 100 characters.';
    if (form.model_number && form.model_number.length > 100) errors.model_number = 'Model number must be less than 100 characters.';
    if (form.serial_number && form.serial_number.length > 100) errors.serial_number = 'Serial number must be less than 100 characters.';
    if (form.location && form.location.length > 200) errors.location = 'Location must be less than 200 characters.';
    setEditErrors(errors);
    return Object.keys(errors).length === 0;
  };

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
    setEditErrors({});
    setIsEditing(true);
  };

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validateEditForm()) {
      return;
    }
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
      setIsEditing(false);
    } catch (err) {
      toast.error(firstErrorMessage(err));
    }
  };

  const handleDecommission = async () => {
    try {
      await updateMut.mutateAsync({ status: 'decommissioned', is_active: false });
      toast.success('Equipment decommissioned.');
    } catch (err) {
      toast.error(firstErrorMessage(err));
    }
  };

  const handleToggleActive = async () => {
    const isActive = eq.is_active;
    try {
      await updateMut.mutateAsync({ is_active: !isActive });
      toast.success(`Equipment ${isActive ? 'deactivated' : 'activated'}.`);
    } catch (err) {
      toast.error(firstErrorMessage(err));
    }
  };

  const handleDelete = async () => {
    try {
      await deleteMut.mutateAsync(ulid ?? '');
      navigate('/maintenance/equipment');
    } catch (err) {
      toast.error(firstErrorMessage(err));
    }
  };

  const set = (k: keyof typeof form, v: string) => {
    setForm(prev => ({ ...prev, [k]: v }));
    // Clear error when user starts typing
    if (editErrors[k as keyof ValidationErrors]) {
      setEditErrors(prev => ({ ...prev, [k]: undefined }));
    }
  };

  const validatePmForm = (): boolean => {
    if (!pmForm.task_name.trim()) {
      return false;
    }
    if (pmForm.task_name.trim().length < 2) {
      return false;
    }
    const freqDays = parseInt(pmForm.frequency_days, 10);
    if (!freqDays || freqDays < 1) {
      return false;
    }
    if (freqDays > 3650) {
      return false;
    }
    return true;
  };

  const handleAddPm = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!validatePmForm()) return;
    const freqDays = parseInt(pmForm.frequency_days, 10);
    try {
      await storePmMut.mutateAsync({
        task_name: pmForm.task_name.trim(),
        frequency_days: freqDays,
        ...(pmForm.last_done_on ? { last_done_on: pmForm.last_done_on } : {}),
      });
      setPmForm({ task_name: '', frequency_days: '', last_done_on: '' });
      setShowAddPm(false);
    } catch (err) {
      toast.error(firstErrorMessage(err));
    }
  };

  const isDecommissioned = eq.status === 'decommissioned';

  const statusBadges = (
    <div className="flex items-center gap-2">
      <StatusBadge status={eq.status}>{eq.status?.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</StatusBadge>
      {!eq.is_active && (
        <span className="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-red-50 text-red-600">
          Inactive
        </span>
      )}
    </div>
  );

  return (
    <div className="max-w-7xl mx-auto">
      <PageHeader
        backTo="/maintenance/equipment"
        title={eq.equipment_code}
        subtitle={eq.name}
        icon={<Settings className="w-5 h-5" />}
        status={statusBadges}
        actions={
          !isEditing && canManage && (
            <div className="flex items-center gap-2 shrink-0">
              {!isDecommissioned && (
                <button
                  type="button"
                  onClick={startEdit}
                  className="flex items-center gap-1.5 px-3 py-1.5 text-sm bg-white text-neutral-700 border border-neutral-300 rounded hover:bg-neutral-50"
                >
                  <Pencil className="w-3.5 h-3.5" />
                  Edit
                </button>
              )}
              {!isDecommissioned && (
                <ConfirmDialog
                  title={eq.is_active ? 'Deactivate this equipment?' : 'Activate this equipment?'}
                  description={eq.is_active
                    ? 'The equipment will be hidden from active lists until reactivated.'
                    : 'The equipment will be made active and visible in all lists.'}
                  confirmLabel={eq.is_active ? 'Deactivate' : 'Activate'}
                  variant={eq.is_active ? 'danger' : 'default'}
                  onConfirm={handleToggleActive}
                >
                  <button
                    type="button"
                    disabled={updateMut.isPending}
                    className="flex items-center gap-1.5 px-3 py-1.5 text-sm bg-white text-neutral-700 border border-neutral-300 rounded hover:bg-neutral-50 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    {eq.is_active ? <PowerOff className="w-3.5 h-3.5" /> : <Power className="w-3.5 h-3.5" />}
                    {eq.is_active ? 'Deactivate' : 'Activate'}
                  </button>
                </ConfirmDialog>
              )}
              {!isDecommissioned && (
                <ConfirmDestructiveDialog
                  title="Decommission equipment?"
                  description="This marks the equipment as permanently retired. All action buttons will be locked."
                  confirmWord="DECOMMISSION"
                  confirmLabel="Decommission"
                  onConfirm={handleDecommission}
                >
                  <button
                    type="button"
                    disabled={updateMut.isPending}
                    className="flex items-center gap-1.5 px-3 py-1.5 text-sm bg-white text-red-600 border border-red-300 rounded hover:bg-red-50 disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <PowerOff className="w-3.5 h-3.5" />
                    Decommission
                  </button>
                </ConfirmDestructiveDialog>
              )}
              <ConfirmDestructiveDialog
                title="Delete equipment?"
                description="This action cannot be undone. All associated PM schedules and work order history will be permanently removed."
                confirmWord="DELETE"
                confirmLabel="Delete"
                onConfirm={handleDelete}
              >
                <button
                  type="button"
                  disabled={deleteMut.isPending}
                  className="flex items-center gap-1.5 px-3 py-1.5 text-sm bg-red-600 text-white border border-red-600 rounded hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <Trash2 className="w-3.5 h-3.5" />
                  Delete
                </button>
              </ConfirmDestructiveDialog>
            </div>
          )
        }
      />

      {/* View mode */}
      {!isEditing && (
        <Card className="mb-5">
          <CardHeader>Equipment Information</CardHeader>
          <CardBody>
            <InfoList columns={2}>
              <InfoRow label="Category" value={eq.category ?? '—'} />
              <InfoRow label="Manufacturer" value={eq.manufacturer ?? '—'} />
              <InfoRow label="Model No." value={eq.model_number ?? '—'} />
              <InfoRow label="Serial No." value={eq.serial_number ?? '—'} />
              <InfoRow label="Location" value={eq.location ?? '—'} />
              <InfoRow label="Date Commissioned" value={eq.commissioned_on ?? '—'} />
            </InfoList>
          </CardBody>
        </Card>
      )}

      {/* Edit mode */}
      {isEditing && (
        <form onSubmit={handleSave} className="bg-white border border-neutral-200 rounded p-6 mb-5 space-y-5">
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Name *</label>
            <input
              type="text"
              className={`${INPUT} ${editErrors.name ? 'border-red-400' : ''}`}
              value={form.name}
              onChange={e => set('name', e.target.value)}
            />
            {editErrors.name && <p className="mt-1 text-xs text-red-600">{editErrors.name}</p>}
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Category</label>
              <input
                type="text"
                className={`${INPUT} ${editErrors.category ? 'border-red-400' : ''}`}
                value={form.category}
                onChange={e => set('category', e.target.value)}
              />
              {editErrors.category && <p className="mt-1 text-xs text-red-600">{editErrors.category}</p>}
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
              <input
                type="text"
                className={`${INPUT} ${editErrors.manufacturer ? 'border-red-400' : ''}`}
                value={form.manufacturer}
                onChange={e => set('manufacturer', e.target.value)}
              />
              {editErrors.manufacturer && <p className="mt-1 text-xs text-red-600">{editErrors.manufacturer}</p>}
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Model No.</label>
              <input
                type="text"
                className={`${INPUT} ${editErrors.model_number ? 'border-red-400' : ''}`}
                value={form.model_number}
                onChange={e => set('model_number', e.target.value)}
              />
              {editErrors.model_number && <p className="mt-1 text-xs text-red-600">{editErrors.model_number}</p>}
            </div>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Serial No.</label>
              <input
                type="text"
                className={`${INPUT} ${editErrors.serial_number ? 'border-red-400' : ''}`}
                value={form.serial_number}
                onChange={e => set('serial_number', e.target.value)}
              />
              {editErrors.serial_number && <p className="mt-1 text-xs text-red-600">{editErrors.serial_number}</p>}
            </div>
            <div>
              <label className="block text-sm font-medium text-neutral-700 mb-1">Location</label>
              <input
                type="text"
                className={`${INPUT} ${editErrors.location ? 'border-red-400' : ''}`}
                value={form.location}
                onChange={e => set('location', e.target.value)}
              />
              {editErrors.location && <p className="mt-1 text-xs text-red-600">{editErrors.location}</p>}
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
              className="px-4 py-2 text-sm bg-white text-neutral-700 border border-neutral-300 rounded hover:bg-neutral-50"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={updateMut.isPending}
              className="px-4 py-2 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {updateMut.isPending ? 'Saving…' : 'Save Changes'}
            </button>
          </div>
        </form>
      )}

      {/* PM Schedules */}
      <Card>
        <CardHeader
          actions={
            !isDecommissioned && canManage && (
              <button
                type="button"
                onClick={() => setShowAddPm(s => !s)}
                className="flex items-center gap-1 px-3 py-1.5 text-sm bg-white text-neutral-700 border border-neutral-300 rounded hover:bg-neutral-50"
              >
                <Plus className="w-3.5 h-3.5" />
                Add PM Schedule
              </button>
            )
          }
        >
          PM Schedules
        </CardHeader>
        <CardBody>
          {showAddPm && (
            <form onSubmit={handleAddPm} className="mb-4 bg-neutral-50 border border-neutral-200 rounded p-4 space-y-3">
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
                    max={3650}
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
                  className="px-3 py-1.5 text-sm bg-white text-neutral-700 border border-neutral-300 rounded hover:bg-neutral-50"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={storePmMut.isPending}
                  className="px-3 py-1.5 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
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
        </CardBody>
      </Card>

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
    </div>
  );
}
