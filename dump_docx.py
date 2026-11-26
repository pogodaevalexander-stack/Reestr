import zipfile, re

z = zipfile.ZipFile(r'D:\Reestr\faxogramm\faxogramm_obrazets.docx')
d = z.read('word/document.xml').decode('utf8')

paras = re.split(r'(?=<w:p[ >])', d)
for i, p in enumerate(paras):
    t = re.findall(r'<w:t[^>]*>([^<]*)</w:t>', p)
    st = re.findall(r'<w:pStyle [^>]*w:val="([^"]*)"', p)
    if t or st:
        print(i, 'STYLE:', st, 'TEXT:', t)

print('---- all style names in doc ----')
names = re.findall(r'name="([^"]*)"', d)
print(names)
