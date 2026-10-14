// n8n Code node — "Run Once for Each Item" mode
// Input: item from "Email Trigger (IMAP)" (fields: .subject, .textHtml, .date)
// Output: one flattened item per email, ready for the Google Sheets "Append row" node

const html = $input.item.json.textHtml || '';

function collapseWs(str) {
  return str.replace(/\s+/g, ' ').trim();
}

function decodeEntities(str) {
  return str
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#0*39;/g, "'");
}

function cleanText(raw) {
  return decodeEntities(collapseWs(raw));
}

// Muse's email body is a simple <tr><td><b>Label:</b></td><td>Value</td></tr> table.
// Kept as an ordered array (not a keyed object) because some forms repeat the same
// label twice (e.g. two unnamed "Custom" fields) - a plain object would drop one.
const rowPattern = /<tr>\s*<td[^>]*><b>([^<]*):<\/b><\/td>\s*<td[^>]*>([\s\S]*?)<\/td>\s*<\/tr>/g;
const rowMatches = html.matchAll(rowPattern);

const rows = [];
for (const row of rowMatches) {
  const label = cleanText(row[1]);
  const valueHtml = row[2].replace(/<[^>]*>/g, ' ');
  const value = cleanText(valueHtml);
  rows.push({ label: label, value: value });
}

function pick(labelName) {
  const found = rows.find(function (r) {
    return r.label === labelName;
  });
  return found ? found.value : '';
}

const websiteMatch = html.match(/Form submitted from website:\s*([\s\S]*?)</);
const ipMatch = html.match(/Visitor IP address:\s*([\s\S]*?)</);

const output = {
  receivedAt: $input.item.json.date || new Date().toISOString(),
  formType: $input.item.json.subject || '',
  name: pick('Name'),
  email: pick('Email'),
  message: pick('Message'),
  website: websiteMatch ? cleanText(websiteMatch[1]) : '',
  visitorIp: ipMatch ? cleanText(ipMatch[1]) : '',
  allFields: JSON.stringify(rows),
};

return { json: output };
