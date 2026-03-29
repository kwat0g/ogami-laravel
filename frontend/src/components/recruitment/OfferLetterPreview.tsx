interface OfferLetterPreviewProps {
  candidateName: string
  positionTitle: string
  departmentName: string
  salary: number
  startDate: string
  employmentType: string
  onClose: () => void
}

export default function OfferLetterPreview({
  candidateName,
  positionTitle,
  departmentName,
  salary,
  startDate,
  employmentType,
  onClose,
}: OfferLetterPreviewProps) {
  const formattedSalary = (salary / 100).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' })

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white p-8 shadow-xl dark:bg-gray-800">
        {/* Close button */}
        <div className="mb-6 flex justify-between">
          <h2 className="text-lg font-bold text-gray-900 dark:text-white">Offer Letter Preview</h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
            <span className="text-xl">&times;</span>
          </button>
        </div>

        {/* Letter content */}
        <div className="prose prose-sm max-w-none space-y-4 text-gray-700 dark:text-gray-300">
          <div className="border-b pb-4">
            <p className="text-lg font-bold text-blue-800 dark:text-blue-400">OGAMI MANUFACTURING CORP.</p>
            <p className="text-xs text-gray-500">Human Resources Department</p>
          </div>

          <p className="text-right text-sm text-gray-500">{new Date().toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })}</p>

          <p>Dear <strong>{candidateName}</strong>,</p>

          <p>
            We are pleased to extend this offer of employment for the position of{' '}
            <strong>{positionTitle}</strong> in the <strong>{departmentName}</strong> department
            at Ogami Manufacturing Corp.
          </p>

          <div className="rounded-lg bg-gray-50 p-4 dark:bg-gray-700">
            <h4 className="mb-2 font-semibold">Employment Details:</h4>
            <table className="w-full text-sm">
              <tbody>
                <tr className="border-b border-gray-200 dark:border-gray-600">
                  <td className="py-2 font-medium">Position</td>
                  <td className="py-2">{positionTitle}</td>
                </tr>
                <tr className="border-b border-gray-200 dark:border-gray-600">
                  <td className="py-2 font-medium">Department</td>
                  <td className="py-2">{departmentName}</td>
                </tr>
                <tr className="border-b border-gray-200 dark:border-gray-600">
                  <td className="py-2 font-medium">Employment Type</td>
                  <td className="py-2">{employmentType}</td>
                </tr>
                <tr className="border-b border-gray-200 dark:border-gray-600">
                  <td className="py-2 font-medium">Monthly Salary</td>
                  <td className="py-2 font-bold text-green-700 dark:text-green-400">{formattedSalary}</td>
                </tr>
                <tr>
                  <td className="py-2 font-medium">Start Date</td>
                  <td className="py-2">{new Date(startDate).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <p>
            Your compensation package includes all statutory benefits mandated by Philippine law,
            including SSS, PhilHealth, Pag-IBIG contributions, and 13th month pay.
          </p>

          <p>
            This offer is contingent upon the satisfactory completion of pre-employment requirements,
            including a medical examination and submission of all required documents.
          </p>

          <p>
            Please confirm your acceptance of this offer within <strong>seven (7) calendar days</strong>{' '}
            from the date of this letter. Should you have any questions, please contact the HR Department.
          </p>

          <p>We look forward to welcoming you to the team.</p>

          <div className="mt-8">
            <p>Sincerely,</p>
            <p className="mt-4 font-semibold">Human Resources Department</p>
            <p className="text-sm text-gray-500">Ogami Manufacturing Corp.</p>
          </div>
        </div>

        {/* Actions */}
        <div className="mt-6 flex justify-end gap-3 border-t pt-4">
          <button
            onClick={onClose}
            className="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300"
          >
            Close
          </button>
          <button
            onClick={() => window.print()}
            className="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500"
          >
            Print / Save as PDF
          </button>
        </div>
      </div>
    </div>
  )
}
