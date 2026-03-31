const fs = require('fs');
let file = fs.readFileSync('frontend/src/pages/delivery/DeliveryReceiptDetailPage.tsx', 'utf8');

file = file.replace(
    "import { useConfirmDeliveryReceipt, useDeliveryReceipt } from '@/hooks/useDelivery';",
    "import { useConfirmDeliveryReceipt, useDeliveryReceipt, useMarkDispatched, useMarkDelivered } from '@/hooks/useDelivery';"
);

file = file.replace(
    "const confirmMut = useConfirmDeliveryReceipt();",
    "const confirmMut = useConfirmDeliveryReceipt();\n  const dispatchMut = useMarkDispatched();\n  const deliverMut = useMarkDelivered();"
);

file = file.replace(
    "const [confirmOpen, setConfirmOpen] = useState(false);",
    "const [confirmOpen, setConfirmOpen] = useState(false);\n  const [dispatchOpen, setDispatchOpen] = useState(false);\n  const [deliverOpen, setDeliverOpen] = useState(false);"
);

file = file.replace(
    "toast.error(firstErrorMessage(err, 'Failed to confirm receipt.'));\n    }\n  };",
    "toast.error(firstErrorMessage(err, 'Failed to confirm receipt.'));\n    }\n  };\n\n  const handleDispatch = async () => { try { await dispatchMut.mutateAsync(dr.ulid); toast.success('Delivery receipt dispatched.'); setDispatchOpen(false); } catch (err) { toast.error(firstErrorMessage(err, 'Failed to dispatch receipt.')); } };\n\n  const handleDeliver = async () => { try { await deliverMut.mutateAsync(dr.ulid); toast.success('Delivery receipt marked as delivered.'); setDeliverOpen(false); } catch (err) { toast.error(firstErrorMessage(err, 'Failed to mark as delivered.')); } };"
);

file = file.replace(
    "dr.status === 'draft' && canManage && (\n            <button\n              type=\"button\"\n              onClick={() => setConfirmOpen(true)}\n              className=\"px-4 py-2 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800\"\n            >\n              Confirm Receipt\n            </button>\n          )",
    "<div className=\"flex items-center gap-2\">\n            {dr.status === 'draft' && canManage && (\n              <button type=\"button\" onClick={() => setConfirmOpen(true)} className=\"px-4 py-2 text-sm bg-neutral-900 text-white rounded hover:bg-neutral-800\">Confirm Receipt</button>\n            )}\n            {dr.status === 'confirmed' && canManage && (\n              <button type=\"button\" onClick={() => setDispatchOpen(true)} className=\"px-4 py-2 text-sm bg-blue-600 text-white rounded hover:bg-blue-700\">Dispatch Goods</button>\n            )}\n            {(dr.status === 'dispatched' || dr.status === 'partially_delivered') && canManage && (\n              <button type=\"button\" onClick={() => setDeliverOpen(true)} className=\"px-4 py-2 text-sm bg-green-600 text-white rounded hover:bg-green-700\">Mark Delivered</button>\n            )}\n          </div>"
);

file = file.replace(
    "loading={confirmMut.isPending}\n      />",
    "loading={confirmMut.isPending}\n      />\n      <ConfirmDialog open={dispatchOpen} onClose={() => setDispatchOpen(false)} onConfirm={handleDispatch} title=\"Dispatch goods?\" description=\"Mark delivery as dispatched from warehouse?\" confirmLabel=\"Dispatch\" variant=\"warning\" loading={dispatchMut.isPending} />\n      <ConfirmDialog open={deliverOpen} onClose={() => setDeliverOpen(false)} onConfirm={handleDeliver} title=\"Mark as Delivered?\" description=\"Confirm goods received by customer?\" confirmLabel=\"Mark Delivered\" variant=\"warning\" loading={deliverMut.isPending} />"
);


fs.writeFileSync('frontend/src/pages/delivery/DeliveryReceiptDetailPage.tsx', file);
