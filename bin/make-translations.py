#!/usr/bin/env python3
"""Build languages/nex-forms-<locale>.po/.mo for visitor-facing strings.
Usage: python3 bin/make-translations.py   (writes all locales)
"""
import struct, os
L = {
"el": {
"This field is required.":"Το πεδίο είναι υποχρεωτικό.",
"Please enter a valid email address.":"Συμπληρώστε ένα έγκυρο email.",
"Please enter a valid URL.":"Συμπληρώστε μια έγκυρη διεύθυνση URL.",
"Please enter a valid phone number.":"Συμπληρώστε έναν έγκυρο αριθμό τηλεφώνου.",
"Please enter a valid number.":"Συμπληρώστε έναν έγκυρο αριθμό.",
"Please enter a valid date.":"Συμπληρώστε μια έγκυρη ημερομηνία.",
"The value must be at least %s.":"Η τιμή πρέπει να είναι τουλάχιστον %s.",
"The value must be at most %s.":"Η τιμή πρέπει να είναι το πολύ %s.",
"Please enter at least %s characters.":"Γράψτε τουλάχιστον %s χαρακτήρες.",
"Please enter no more than %s characters.":"Γράψτε έως %s χαρακτήρες.",
"Please match the requested format.":"Η μορφή δεν είναι σωστή.",
"Please select an option.":"Επιλέξτε μία επιλογή.",
"Please check the highlighted fields.":"Ελέγξτε τα επισημασμένα πεδία.",
"Sending…":"Αποστολή…",
"Could not reach the server. Please check your connection and try again.":"Δεν ήταν δυνατή η σύνδεση. Ελέγξτε τη σύνδεσή σας και δοκιμάστε ξανά.",
"Please complete the security check.":"Ολοκληρώστε τον έλεγχο ασφαλείας.",
"That was quick! Please wait a moment and try again.":"Πολύ γρήγορα! Περιμένετε λίγο και δοκιμάστε ξανά.",
"Leave this field empty":"Αφήστε αυτό το πεδίο κενό",
"— Select —":"— Επιλέξτε —",
"Country calling code":"Κωδικός χώρας",
"This form is not available.":"Η φόρμα δεν είναι διαθέσιμη.",
"The submission is too large.":"Η υποβολή είναι πολύ μεγάλη.",
"Too many submissions. Please try again later.":"Πάρα πολλές υποβολές. Δοκιμάστε ξανά αργότερα.",
"The security check failed. Please try again.":"Ο έλεγχος ασφαλείας απέτυχε. Δοκιμάστε ξανά.",
"Your message could not be sent right now. Please try again later or contact us directly.":"Το μήνυμά σας δεν στάλθηκε. Δοκιμάστε ξανά αργότερα ή επικοινωνήστε απευθείας μαζί μας.",
"Invalid value.":"Μη έγκυρη τιμή.",
"Yes":"Ναι",
"No":"Όχι",
"Contact form":"Φόρμα επικοινωνίας",
"Name":"Ονοματεπώνυμο",
"Email":"Email",
"Phone":"Τηλέφωνο",
"Message":"Μήνυμα",
"Send message":"Αποστολή",
"Thank you! Your message has been sent.":"Ευχαριστούμε! Το μήνυμά σας στάλθηκε.",
"Untitled form":"Φόρμα χωρίς τίτλο",
"Option 1":"Επιλογή 1",
"Option 2":"Επιλογή 2",
"[%s] NexForms test email":"[%s] Δοκιμαστικό email NexForms",
"This is a test message from NexForms. If you received it, form notifications will be delivered through your current mail setup.":"Δοκιμαστικό μήνυμα από το NexForms. Αν το λάβατε, οι ειδοποιήσεις των φορμών θα παραδίδονται μέσω της τρέχουσας ρύθμισης email.",
"NexForms: form not found.":"NexForms: η φόρμα δεν βρέθηκε.",
"NexForms: this form is a draft and is hidden from visitors.":"NexForms: η φόρμα είναι πρόχειρη και δεν εμφανίζεται στους επισκέπτες.",
"Send another message":"Αποστολή νέου μηνύματος",
"We received your message {ticket}":"Λάβαμε το μήνυμά σας {ticket}",
"Hello {name},\n\nThank you for contacting us. We have received your request and will get back to you as soon as possible.\n\nYour reference number: {ticket}\n\n{site_name}":"Γεια σας {name},\n\nΣας ευχαριστούμε που επικοινωνήσατε μαζί μας. Λάβαμε το αίτημά σας και θα σας απαντήσουμε το συντομότερο δυνατό.\n\nΑριθμός αναφοράς: {ticket}\n\n{site_name}",
},
"bg_BG": {
"This field is required.":'Това поле е задължително.',
"Please enter a valid email address.":'Моля, въведете валиден имейл адрес.',
"Please enter a valid URL.":'Моля, въведете валиден URL адрес.',
"Please enter a valid phone number.":'Моля, въведете валиден телефонен номер.',
"Please enter a valid number.":'Моля, въведете валидно число.',
"Please enter a valid date.":'Моля, въведете валидна дата.',
"The value must be at least %s.":'Стойността трябва да е поне %s.',
"The value must be at most %s.":'Стойността трябва да е най-много %s.',
"Please enter at least %s characters.":'Моля, въведете поне %s знака.',
"Please enter no more than %s characters.":'Моля, въведете не повече от %s знака.',
"Please match the requested format.":'Моля, спазете изисквания формат.',
"Please select an option.":'Моля, изберете опция.',
"Please check the highlighted fields.":'Моля, проверете маркираните полета.',
"Sending…":'Изпращане…',
"Could not reach the server. Please check your connection and try again.":'Няма връзка със сървъра. Проверете връзката си и опитайте отново.',
"Please complete the security check.":'Моля, завършете проверката за сигурност.',
"That was quick! Please wait a moment and try again.":'Твърде бързо! Изчакайте малко и опитайте отново.',
"Leave this field empty":'Оставете това поле празно',
"— Select —":'— Изберете —',
"Country calling code":'Код на държавата',
"This form is not available.":'Формулярът не е наличен.',
"The submission is too large.":'Изпратените данни са твърде големи.',
"Too many submissions. Please try again later.":'Твърде много изпращания. Моля, опитайте по-късно.',
"The security check failed. Please try again.":'Проверката за сигурност не бе успешна. Моля, опитайте отново.',
"Your message could not be sent right now. Please try again later or contact us directly.":'Съобщението ви не можа да бъде изпратено. Моля, опитайте по-късно или се свържете директно с нас.',
"Invalid value.":'Невалидна стойност.',
"Yes":'Да',
"No":'Не',
"Contact form":'Форма за контакт',
"Name":'Име',
"Email":'Имейл',
"Phone":'Телефон',
"Message":'Съобщение',
"Send message":'Изпрати съобщение',
"Thank you! Your message has been sent.":'Благодарим ви! Съобщението ви беше изпратено.',
"Untitled form":'Формуляр без име',
"Option 1":'Опция 1',
"Option 2":'Опция 2',
"[%s] NexForms test email":'[%s] Тестов имейл от NexForms',
"This is a test message from NexForms. If you received it, form notifications will be delivered through your current mail setup.":'Това е тестово съобщение от NexForms. Ако сте го получили, известията от формулярите ще се доставят чрез текущата ви настройка за поща.',
"NexForms: form not found.":'NexForms: формулярът не е намерен.',
"NexForms: this form is a draft and is hidden from visitors.":'NexForms: формулярът е чернова и не се показва на посетителите.',
"Send another message":'Изпратете ново съобщение',
"We received your message {ticket}":"Получихме съобщението ви {ticket}",
"Hello {name},\n\nThank you for contacting us. We have received your request and will get back to you as soon as possible.\n\nYour reference number: {ticket}\n\n{site_name}":"Здравейте, {name},\n\nБлагодарим ви, че се свързахте с нас. Получихме запитването ви и ще ви отговорим възможно най-скоро.\n\nВашият номер за справка: {ticket}\n\n{site_name}",
},
}
PLURAL = {"el": "nplurals=2; plural=(n != 1);", "bg_BG": "nplurals=2; plural=(n != 1);"}

def esc(s): return s.replace('\\','\\\\').replace('"','\\"').replace('\n','\\n')

def build(locale, T, out):
    header = "Content-Type: text/plain; charset=UTF-8\nLanguage: %s\nPlural-Forms: %s\n" % (locale, PLURAL[locale])
    po = ['msgid ""', 'msgstr ""'] + ['"%s\\n"' % h for h in header.strip().split("\n")] + ['']
    for k, v in T.items():
        po += ['msgid "%s"' % esc(k), 'msgstr "%s"' % esc(v), '']
    open(out + '.po', 'w', encoding='utf-8').write('\n'.join(po))
    items = sorted([("", header)] + list(T.items()), key=lambda kv: kv[0].encode())
    ids = b''; strs = b''; offs = []
    for k, v in items:
        kb = k.encode(); vb = v.encode()
        offs.append((len(kb), len(ids), len(vb), len(strs))); ids += kb + b'\0'; strs += vb + b'\0'
    n = len(items); ko = 28; vo = ko + n * 8; ds = vo + n * 8
    kt = b''.join(struct.pack('<II', l, ds + o) for l, o, _, _ in offs)
    vt = b''.join(struct.pack('<II', l, ds + len(ids) + o) for _, _, l, o in offs)
    open(out + '.mo', 'wb').write(struct.pack('<IIIIIII', 0x950412de, 0, n, ko, vo, 0, 0) + kt + vt + ids + strs)

root = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'languages')
for locale, T in L.items():
    build(locale, T, os.path.join(root, 'nex-forms-' + locale))
    print(locale, len(T), 'strings')
