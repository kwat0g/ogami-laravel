const fs = require('fs');
let text = fs.readFileSync('src/pages/approvals/VpApprovalsDashboardPage.tsx', 'utf8');

text = text.replace(
  /Cancel<\/button>\n                className="text-sm px-4 py-2 bg-neutral-900/g,
  `Cancel</button>\n                <button onClick={() => gaProcess.mutate({ id: processId!, action_taken: actionTaken, remarks: leaveRemarks }, { onSuccess: () => { setProcessId(null); setLeaveRemarks(''); setActionTaken('approved_with_pay') } })} disabled={gaProcess.isPending} className="text-sm px-4 py-2 bg-neutral-900`
);

text = text.replace(
  /Cancel<\/button>\n                className="text-sm px-4 py-2 bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50">\n                \{vpNote/g,
  `Cancel</button>\n                <button onClick={() => vpNote.mutate({ id: vpNoteId!, vp_remarks: vpRemarks }, { onSuccess: () => { setVpNoteId(null); setVpRemarks('') } })} disabled={vpNote.isPending} className="text-sm px-4 py-2 bg-neutral-900 text-white rounded hover:bg-neutral-800 disabled:opacity-50">\n                {vpNote`
);

fs.writeFileSync('src/pages/approvals/VpApprovalsDashboardPage.tsx', text);
console.log('Fixed vp2');
