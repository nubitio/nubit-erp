export function parseCsv(text: string): string[][] {
  const rows: string[][] = [];
  let row: string[] = [];
  let cell = '';
  let inQuotes = false;

  const pushCell = () => {
    row.push(cell.trim());
    cell = '';
  };

  const pushRow = () => {
    if (row.some((value) => value !== '')) rows.push(row);
    row = [];
  };

  const source = text.replace(/^\uFEFF/, '');
  for (let i = 0; i < source.length; i += 1) {
    const char = source[i];
    const next = source[i + 1];
    if (inQuotes) {
      if (char === '"' && next === '"') {
        cell += '"';
        i += 1;
      } else if (char === '"') {
        inQuotes = false;
      } else {
        cell += char;
      }
      continue;
    }
    if (char === '"') {
      inQuotes = true;
    } else if (char === ',' || char === ';') {
      pushCell();
    } else if (char === '\n') {
      pushCell();
      pushRow();
    } else if (char !== '\r') {
      cell += char;
    }
  }
  pushCell();
  pushRow();
  return rows;
}

export function csvObjects(text: string): Array<Record<string, string>> {
  const [headerRow, ...body] = parseCsv(text);
  if (!headerRow) return [];
  const headers = headerRow.map((header) => header.trim().toLowerCase());
  return body.map((line) => {
    const record: Record<string, string> = {};
    headers.forEach((header, index) => {
      record[header] = line[index] ?? '';
    });
    return record;
  });
}

export function pick(row: Record<string, string>, aliases: string[]): string {
  for (const alias of aliases) {
    const value = row[alias];
    if (value) return value;
  }
  return '';
}
