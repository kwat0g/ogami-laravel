import { useParams, useNavigate } from 'react-router-dom'
import { ArrowLeft, UserCheck, XCircle } from 'lucide-react'
import { useLead, useConvertLead, useDisqualifyLead } from '@/hooks/useCRM'
import { PageHeader } from '@/components/ui/PageHeader'
import { Card } from '@/components/ui/Card'
import StatusBadge from '@/components/ui/StatusBadge'
import SkeletonLoader from '@/components/ui/SkeletonLoader'
import { useState } from 'react'

export default function LeadDetailPage() {
  const { ulid } = useParams<{ ulid: string }>()
  const navigate = useNavigate()
  const { data: lead, isLoading } = useLead(ulid ?? '')
  const convertMutation = useConvertLead(ulid ?? '')
  const disqualifyMutation = useDisqualifyLead(ulid ?? '')
  const [disqualifyReason, setDisqualifyReason] = useState('')

  if (isLoading) return <SkeletonLoader rows={6} />
  if (!lead) return <div className="p-6 text-neutral-500">Lead not found</div>

  const canConvert = !['converted', 'disqualified'].includes(lead.status)

  return (
    <div className="space-y-6">
      <PageHeader
        title={lead.company_name}
        icon={<button onClick={() => navigate('/crm/leads')} className="p-1 hover:bg-neutral-100 rounded"><ArrowLeft className="w-5 h-5" /></button>}
        actions={
          <div className="flex gap-2">
            {canConvert && (
              <>
                <button
                  className="btn-primary"
                  onClick={() => convertMutation.mutate({ create_opportunity: true })}
                  disabled={convertMutation.isPending}
                >
                  <UserCheck className="w-4 h-4" /> Convert to Customer
                </button>
                <button
                  className="btn-danger"
                  onClick={() => {
                    const reason = prompt('Reason for disqualification:')
                    if (reason) disqualifyMutation.mutate({ reason })
                  }}
                  disabled={disqualifyMutation.isPending}
                >
                  <XCircle className="w-4 h-4" /> Disqualify
                </button>
              </>
            )}
          </div>
        }
      />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card className="p-6 space-y-4">
          <h3 className="font-semibold text-lg">Lead Information</h3>
          <div className="grid grid-cols-2 gap-4 text-sm">
            <div>
              <span className="text-neutral-500">Company</span>
              <p className="font-medium">{lead.company_name}</p>
            </div>
            <div>
              <span className="text-neutral-500">Contact</span>
              <p className="font-medium">{lead.contact_name}</p>
            </div>
            <div>
              <span className="text-neutral-500">Email</span>
              <p>{lead.email || '-'}</p>
            </div>
            <div>
              <span className="text-neutral-500">Phone</span>
              <p>{lead.phone || '-'}</p>
            </div>
            <div>
              <span className="text-neutral-500">Source</span>
              <p className="capitalize">{lead.source.replace('_', ' ')}</p>
            </div>
            <div>
              <span className="text-neutral-500">Status</span>
              <p><StatusBadge status={lead.status} /></p>
            </div>
            <div>
              <span className="text-neutral-500">Assigned To</span>
              <p>{lead.assignedTo?.name || '-'}</p>
            </div>
            <div>
              <span className="text-neutral-500">Created</span>
              <p>{new Date(lead.created_at).toLocaleDateString()}</p>
            </div>
          </div>
          {lead.notes && (
            <div>
              <span className="text-neutral-500 text-sm">Notes</span>
              <p className="mt-1 text-sm whitespace-pre-wrap">{lead.notes}</p>
            </div>
          )}
        </Card>

        {lead.converted_at && (
          <Card className="p-6 space-y-4">
            <h3 className="font-semibold text-lg">Conversion Details</h3>
            <div className="text-sm space-y-2">
              <p>Converted on: <strong>{new Date(lead.converted_at).toLocaleDateString()}</strong></p>
              <p>Customer ID: <strong>{lead.converted_customer_id}</strong></p>
            </div>
          </Card>
        )}
      </div>
    </div>
  )
}
