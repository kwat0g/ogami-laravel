const fs = require('fs');
let content = fs.readFileSync('frontend/src/pages/fixed-assets/FixedAssetDetailPage.tsx', 'utf8');

const toAdd = `
      {(!asset.depreciation_entries || asset.depreciation_entries.length === 0) ? (
        <Card>
          <CardBody>
            <h3 className="font-semibold text-neutral-800 mb-2">Depreciation Schedule</h3>
            <p className="text-neutral-500 text-sm">No depreciation entries recorded yet.</p>
          </CardBody>
        </Card>
      ) : (
        <Card>
          <CardBody>
            <h3 className="font-semibold text-neutral-800 mb-4">Depreciation Schedule</h3>
            <div className="overflow-x-auto">
              <table className="w-full text-sm text-left border border-neutral-200">
                <thead className="text-xs text-neutral-500 bg-neutral-50 border-b border-neutral-200">
                  <tr>
                    <th className="px-4 py-3 font-medium">Date</th>
                    <th className="px-4 py-3 font-medium">Period</th>
                    <th className="px-4 py-3 font-medium text-right">Amount</th>
                    <th className="px-4 py-3 font-medium text-right">Book Value</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-neutral-100">
                  {asset.depreciation_entries.map((entry: any) => (
                    <tr key={entry.id}>
                      <td className="px-4 py-3 text-neutral-800">{entry.entry_date}</td>
                      <td className="px-4 py-3 text-neutral-600">{entry.fiscal_period?.name || '—'}</td>
                      <td className="px-4 py-3 text-red-600 font-medium text-right">
                        - {fmt(entry.amount_centavos)}
                      </td>
                      <td className="px-4 py-3 text-neutral-800 font-medium text-right">
                        {fmt(entry.book_value_after_centavos)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </CardBody>
        </Card>
      )}
    </div>
  )
}
`;

content = content.replace(/    <\/div>\s* \)\s*}/g, toAdd);
fs.writeFileSync('frontend/src/pages/fixed-assets/FixedAssetDetailPage.tsx', content);
