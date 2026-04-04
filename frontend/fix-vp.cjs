const fs = require('fs');
let text = fs.readFileSync('src/pages/approvals/VpApprovalsDashboardPage.tsx', 'utf8');
text = text.replace(/>Cancel<\/button>\n                <button onClick=\{\(\) => handleGaProcess\(\)\} disabled=\{gaProcess\.isPending\}/g, '>Cancel</button>');
fs.writeFileSync('src/pages/approvals/VpApprovalsDashboardPage.tsx', text);
