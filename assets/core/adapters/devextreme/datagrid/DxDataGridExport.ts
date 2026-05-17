import { DataGridTypes } from 'devextreme-react/data-grid';
import { jsPDF as JsPdf } from 'jspdf';
import { saveAs } from 'file-saver';
import { Workbook } from 'exceljs';
import { exportDataGrid as exportDataGridToPdf } from 'devextreme/pdf_exporter';
import { exportDataGrid as exportDataGridToXLSX } from 'devextreme/excel_exporter';

export const EXPORT_FORMATS = ['xlsx', 'pdf'];

/**
 * Handles DevExtreme DataGrid export events.
 * Supports PDF (jsPDF) and XLSX (ExcelJS) formats.
 */
export function handleDataGridExport(e: DataGridTypes.ExportingEvent, title: string): void {
  if (e.format === 'pdf') {
    const doc = new JsPdf();
    exportDataGridToPdf({
      jsPDFDocument: doc,
      component: e.component,
    }).then(() => {
      doc.save(title + '.pdf');
    });
  } else {
    const workbook = new Workbook();
    const worksheet = workbook.addWorksheet(title);

    exportDataGridToXLSX({
      component: e.component,
      worksheet,
      autoFilterEnabled: true,
    }).then(() => {
      workbook.xlsx.writeBuffer().then((buffer) => {
        saveAs(new Blob([buffer], { type: 'application/octet-stream' }), title + '.xlsx');
      });
    });
    e.cancel = true;
  }
}
