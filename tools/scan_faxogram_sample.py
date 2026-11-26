from docx import Document
from pathlib import PureWindowsPath

path = r'D:\Reestr\faxogramm\faxogramm_obrazets.docx'
doc = Document(path)
section = doc.sections[0]
print('Страницы:', len(doc.sections))
print('A4:', round(section.page_width / 360000, 2), 'x', round(section.page_height / 360000, 2), 'см')
print('Поля см:', round(section.top_margin / 360000, 2), round(section.right_margin / 360000, 2), round(section.bottom_margin / 360000, 2), round(section.left_margin / 360000, 2))
print('Параграфов:', len(doc.paragraphs))
for i, paragraph in enumerate(doc.paragraphs):
    text = paragraph.text.strip()
    if text:
        print(f'{i}: {text}')
