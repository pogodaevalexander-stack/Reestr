import zipfile, os

z = zipfile.ZipFile(r'D:\Reestr\faxogramm\faxogramm_obrazets.docx')
names = [n for n in z.namelist() if n.startswith('word/media/')]
print(names)
for n in names:
    data = z.read(n)
    out = n.replace('word/media/', 'D:/Reestr/faxogramm/')
    with open(out, 'wb') as f:
        f.write(data)
    print('WROTE', out, len(data))
