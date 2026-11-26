import zipfile, re

z = zipfile.ZipFile(r'D:\Reestr\faxogramm\faxogramm_obrazets.docx')
d = z.read('word/document.xml').decode('utf8')

print('LEN', len(d))
print('--- w:p elements with text ---')
for m in re.finditer(r'<w:p[ >].*?</w:p>', d, flags=re.S):
    p = m.group(0)
    txt = ''.join(re.findall(r'<w:t[^>]*>([^<]*)</w:t>', p))
    st = re.findall(r'<w:pStyle[^>]*w:val="([^"]*)"', p)
    spc_before = re.findall(r'<w:pPr>.*?<w:spacing[^>]*w:before="([^"]*)"', p, flags=re.S)
    spc_after = re.findall(r'<w:pPr>.*?<w:spacing[^>]*w:after="([^"]*)"', p, flags=re.S)
    jc = re.findall(r'<w:jc[^/]*/>', p)
    print('P', st, 'TXT', repr(txt), 'SPB', spc_before, 'SPA', spc_after, 'JC', jc)

print('--- headings style names ---')
for m in re.finditer(r'<w:style [^>]*w:type="heading"[^>]*>', d):
    s = m.group(0)
    nm = re.search(r'name="([^"]*)"', s).group(1)
    lvl = re.findall(r'<w:outlineLvl[^/]*?val="([^"]*)"', s)
    print(s[:400])
    print('NAME:', nm, 'LVL:', lvl)

print('--- fonts ---')
for m in re.finditer(r'<w:font [^>]*>', d):
    print(m.group(0))
print('--- run props sample ---')
for m in re.finditer(r'<w:rPr>.*?</w:rPr>', d, flags=re.S):
    print(m.group(0)[:600])
    break
