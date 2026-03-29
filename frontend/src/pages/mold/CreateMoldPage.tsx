import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ArrowLeft } from 'lucide-react';
import { toast } from 'sonner';
import { PageHeader } from '@/components/ui/PageHeader';
import { firstErrorMessage } from '@/lib/errorHandler'
import { useCreateMold } from '@/hooks/useMold';
import type { MoldStatus } from '@/types/mold';

const INPUT = 'w-full border border-neutral-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-neutral-400 bg-white [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none';

export default function CreateMoldPage(): React.ReactElement {
  const navigate = useNavigate();
  const createMut = useCreateMold();

  const [form, setForm] = useState({
    name: '',
    description: '',
    cavity_count: '',
    material: '',
    location: '',
    max_shots: '',
    status: 'active' as MoldStatus,
  });

  const set = (k: keyof typeof form, v: string) => setForm(prev => ({ ...prev, [k]: v }));

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.name.trim()) { toast.error('Name is required.'); return; }
    const cavityCount = parseInt(form.cavity_count, 10);
    if (!cavityCount || cavityCount < 1) { toast.error('Cavity count must be at least 1.'); return; }
    try {
      await createMut.mutateAsync({
        name: form.name.trim(),
        description: form.description || undefined,
        cavity_count: cavityCount,
        material: form.material || undefined,
        location: form.location || undefined,
        max_shots: form.max_shots ? parseInt(form.max_shots, 10) : undefined,
        status: form.status,
      });
      toast.success('Mold created.');
      navigate('/mold/masters');
    } catch (_err) {
      toast.error(firstErrorMessage(err, 'Failed to create mold.'));
    }
  };

  return (
    <div className="max-w-4xl mx-auto">
      <div className="flex items-center gap-3 mb-6">
        <button
          type="button"
          onClick={() => navigate('/mold/masters')}
          className="p-2 rounded-lg border border-neutral-200 bg-white hover:bg-neutral-50 hover:border-neutral-300 text-neutral-500"
          aria-label="Back to Mold Masters"
        >
          <ArrowLeft className="w-4 h-4" />
        </button>
        <PageHeader title="New Mold" backTo="/mold/masters" />
      </div>

      <form onSubmit={handleSubmit} className="bg-white border border-neutral-200 rounded p-6 space-y-5">
        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Name *</label>
          <input
            type="text"
            className={INPUT}
            value={form.name}
            onChange={e => set('name', e.target.value)}
            required
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Description</label>
          <textarea
            className={INPUT}
            rows={2}
            value={form.description}
            onChange={e => set('description', e.target.value)}
          />
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Cavity Count *</label>
            <input
              type="number"
              min={1}
              className={INPUT}
              value={form.cavity_count}
              onChange={e => set('cavity_count', e.target.value)}
              required
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Max Shots *</label>
            <input
              type="number"
              min={1}
              className={INPUT}
              value={form.max_shots}
              onChange={e => set('max_shots', e.target.value)}
            />
          </div>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Material</label>
            <input
              type="text"
              className={INPUT}
              value={form.material}
              onChange={e => set('material', e.target.value)}
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-neutral-700 mb-1">Location</label>
            <input
              type="text"
              className={INPUT}
              value={form.location}
              onChange={e => set('location', e.target.value)}
            />
          </div>
        </div>

        <div>
          <label className="block text-sm font-medium text-neutral-700 mb-1">Status *</label>
          <select
            className={INPUT}
            value={form.status}
            onChange={e => set('status', e.target.value)}
          >
            <option value="active">Active</option>
            <option value="under_maintenance">Under Maintenance</option>
            <option value="retired">Retired</option>
          </select>
        </div>

        <div className="flex justify-end gap-3 pt-1">
          <button
            type="button"
            onClick={() => navigate('/mold/masters')}
            className="px-4 py-2 text-sm border border-neutral-300 rounded hover:bg-neutral-50"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={createMut.isPending}
            className="px-4 py-2 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {createMut.isPending ? 'Creating…' : 'Create Mold'}
          </button>
        </div>
      </form>
    </div>
  );
}
