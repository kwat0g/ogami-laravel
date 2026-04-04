const fs = require('fs');
const file = '/home/kwat0g/ogamiPHP/ogami-laravel/frontend/src/pages/admin/SystemSettingsPage.tsx';
let content = fs.readFileSync(file, 'utf8');
content = content.replace(/if \(hasChanges\) \{\n      setShowCancelConfirm\(true\)\n          \} else \{\n        toast.error\("Failed to update settings", \{\n      handleCancelConfirm\(\)\n    \}/g, 'if (hasChanges) {\n      setShowCancelConfirm(true)\n    } else {\n      handleCancelConfirm()\n    }');
fs.writeFileSync(file, content);
