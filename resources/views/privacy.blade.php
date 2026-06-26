<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Privacy Policy — Booking Saver for HelloOtel</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      font-size: 16px;
      line-height: 1.7;
      color: #1a1a2e;
      background: #f8f9fa;
      padding: 2rem 1rem;
    }
    .wrap {
      max-width: 760px;
      margin: 0 auto;
    }
    .lang-bar {
      display: flex;
      flex-wrap: wrap;
      gap: .5rem;
      margin-bottom: 1.25rem;
    }
    .lang-bar button {
      padding: .35rem .9rem;
      border: 2px solid #d0d5e8;
      border-radius: 6px;
      background: #fff;
      color: #444;
      font-size: .88rem;
      cursor: pointer;
      transition: border-color .15s, background .15s, color .15s;
    }
    .lang-bar button.active,
    .lang-bar button:hover {
      border-color: #3b5bdb;
      background: #3b5bdb;
      color: #fff;
    }
    .card {
      background: #fff;
      border-radius: 12px;
      padding: 2.5rem 3rem;
      box-shadow: 0 2px 12px rgba(0,0,0,.07);
    }
    .lang-section { display: none; }
    .lang-section.visible { display: block; }
    h1 { font-size: 1.9rem; margin-bottom: .35rem; color: #111; }
    .updated { color: #888; font-size: .9rem; margin-bottom: 2rem; }
    h2 { font-size: 1.15rem; margin: 2rem 0 .5rem; color: #222; }
    p  { margin-bottom: 1rem; }
    ul { margin: .5rem 0 1rem 1.4rem; }
    ul li { margin-bottom: .35rem; }
    a  { color: #3b5bdb; }
    .contact { background: #f1f3f9; border-radius: 8px; padding: 1rem 1.25rem; margin-top: 2rem; }
    @media (max-width: 600px) {
      .card { padding: 1.5rem 1.25rem; }
    }
  </style>
</head>
<body>
<div class="wrap">
  <div class="lang-bar">
    <button onclick="switchLang('en')" class="active" id="btn-en">English</button>
    <button onclick="switchLang('de')" id="btn-de">Deutsch</button>
    <button onclick="switchLang('tr')" id="btn-tr">Türkçe</button>
    <button onclick="switchLang('ru')" id="btn-ru">Русский</button>
    <button onclick="switchLang('uk')" id="btn-uk">Українська</button>
  </div>

  <div class="card">

    <!-- ═══════════════ ENGLISH ═══════════════ -->
    <section class="lang-section visible" id="lang-en">
      <h1>Privacy Policy</h1>
      <p class="updated">Last updated: {{ date('F j, Y') }}</p>
      <p>This Privacy Policy describes how the <strong>Booking Saver for HelloOtel</strong> Chrome extension ("Extension") collects, uses, and stores information when you use it.</p>

      <h2>1. What data we collect</h2>
      <p>The Extension collects only the data required to save and process hotel booking information:</p>
      <ul>
        <li><strong>Booking data</strong> — hotel name, booking number, check-in and check-out dates, number of guests, price, and related fields read from the pages of supported tour operator portals.</li>
        <li><strong>Login credentials</strong> — your HelloOtel username and password, used only for authentication. Passwords are not stored.</li>
        <li><strong>Authentication token</strong> — stored locally in <code>chrome.storage.local</code> on your device to keep you signed in between browser sessions.</li>
      </ul>
      <p>The extension does not collect browser history, personal data unrelated to bookings, or any information from pages outside the supported tour operator portals.</p>

      <h2>2. How we use the data</h2>
      <ul>
        <li>To save booking records to the operator's private database at <strong>booking-configurator.hellootel.com</strong>.</li>
        <li>To transfer confirmed bookings to the HelloOtel bonus system on behalf of the operator.</li>
        <li>To display saved bookings in the extension popup and on the bookings management page.</li>
      </ul>
      <p>Data is sent to HelloOtel only after the user explicitly clicks the "Send to HelloOtel" button and confirms the booking in the dialog. No automatic or background data transfer is performed.</p>

      <h2>3. Data storage</h2>
      <ul>
        <li>Your authentication token is stored locally in <code>chrome.storage.local</code> and never transmitted except as a Bearer header to our API.</li>
        <li>Booking records are stored on our secure server and accessible only to authenticated and authorized travel agencies within your organization.</li>
      </ul>

      <h2>4. Permissions used</h2>
      <ul>
        <li><strong>storage</strong> — to store your session token locally.</li>
        <li><strong>Permissions for specific domains</strong> — allow the extension to work on supported booking pages and communicate with the HelloOtel API. The extension is not granted access to other websites.</li>
      </ul>
      <p>The extension does not modify the content of third-party pages — it only adds the "Send to HelloOtel" button. No data is read or sent without an explicit action by the user.</p>

      <h2>5. Data security</h2>
      <p>All communication between the extension and our server is encrypted using HTTPS. Access to booking data is restricted to authenticated users with a valid token.</p>

      <h2>6. Children's privacy</h2>
      <p>The extension is intended for use by travel industry professionals aged 18 and older. It is not directed at children under 18, and we do not knowingly collect data from minors.</p>

      <h2>7. Changes to this policy</h2>
      <p>We may update this Privacy Policy from time to time. The "Last updated" date at the top of this page will reflect any changes. Continued use of the Extension after changes constitutes acceptance of the updated policy.</p>

      <div class="contact">
        <strong>Contact</strong><br>
        If you have questions about this Privacy Policy, please contact us at <a href="mailto:info@hellootel.app">info@hellootel.app</a>.
      </div>
    </section>

    <!-- ═══════════════ DEUTSCH ═══════════════ -->
    <section class="lang-section" id="lang-de">
      <h1>Datenschutzerklärung</h1>
      <p class="updated">Zuletzt aktualisiert: {{ date('d.m.Y') }}</p>
      <p>Diese Datenschutzerklärung beschreibt, wie die Chrome-Erweiterung <strong>Booking Saver for HelloOtel</strong> („Erweiterung") Informationen erfasst, verwendet und speichert.</p>

      <h2>1. Welche Daten wir erheben</h2>
      <p>Die Erweiterung erhebt ausschließlich Daten, die zur Speicherung und Verarbeitung von Hotelbuchungen erforderlich sind:</p>
      <ul>
        <li><strong>Buchungsdaten</strong> — Hotelname, Buchungsnummer, An- und Abreisedaten, Gästezahl, Preis und zugehörige Felder — aus den Seiten unterstützter Reiseveranstalter-Portale gelesen.</li>
        <li><strong>Anmeldedaten</strong> — HelloOtel-Benutzername und -Passwort, ausschließlich zur Authentifizierung verwendet. Passwörter werden nicht gespeichert.</li>
        <li><strong>Authentifizierungstoken</strong> — wird lokal in <code>chrome.storage.local</code> gespeichert, um Sie zwischen Browser-Sitzungen angemeldet zu halten.</li>
      </ul>
      <p>Die Erweiterung erhebt keinen Browserverlauf, keine buchungsunabhängigen personenbezogenen Daten und keine Informationen von Seiten außerhalb der unterstützten Reiseveranstalter-Portale.</p>

      <h2>2. Wie wir die Daten verwenden</h2>
      <ul>
        <li>Zum Speichern von Buchungsdatensätzen in der privaten Datenbank des Betreibers unter <strong>booking-configurator.hellootel.com</strong>.</li>
        <li>Zur Übermittlung bestätigter Buchungen an das HelloOtel-Bonussystem im Auftrag des Betreibers.</li>
        <li>Zur Anzeige gespeicherter Buchungen im Erweiterungs-Popup und auf der Buchungsverwaltungsseite.</li>
      </ul>
      <p>Daten werden erst nach dem expliziten Klick des Nutzers auf „An HelloOtel senden" und der Bestätigung im Dialog übermittelt. Eine automatische oder Hintergrundübertragung findet nicht statt.</p>

      <h2>3. Datenspeicherung</h2>
      <ul>
        <li>Ihr Authentifizierungstoken wird lokal in <code>chrome.storage.local</code> gespeichert und nur als Bearer-Header an unsere API übertragen.</li>
        <li>Buchungsdatensätze werden auf unserem sicheren Server gespeichert und sind nur authentifizierten und autorisierten Betreibern Ihrer Organisation zugänglich.</li>
      </ul>

      <h2>4. Verwendete Berechtigungen</h2>
      <ul>
        <li><strong>storage</strong> — zur lokalen Speicherung des Sitzungstokens.</li>
        <li><strong>Berechtigungen für bestimmte Domains</strong> — ermöglichen der Erweiterung, auf unterstützten Buchungsseiten zu arbeiten und mit der HelloOtel API zu kommunizieren. Zugriff auf andere Websites wird nicht gewährt.</li>
      </ul>
      <p>Die Erweiterung verändert keine Inhalte von Drittanbieter-Seiten — sie fügt nur die Schaltfläche „An HelloOtel senden" hinzu. Keine Daten werden ohne explizite Nutzeraktion gelesen oder gesendet.</p>

      <h2>5. Datensicherheit</h2>
      <p>Die gesamte Kommunikation zwischen der Erweiterung und unserem Server ist durch HTTPS verschlüsselt. Der Zugriff auf Buchungsdaten ist auf authentifizierte Nutzer mit gültigem Token beschränkt.</p>

      <h2>6. Datenschutz für Kinder</h2>
      <p>Die Erweiterung richtet sich an Fachleute der Reisebranche ab 18 Jahren. Sie ist nicht für Kinder unter 18 Jahren bestimmt, und wir erheben wissentlich keine Daten von Minderjährigen.</p>

      <h2>7. Änderungen dieser Richtlinie</h2>
      <p>Wir können diese Datenschutzerklärung von Zeit zu Zeit aktualisieren. Das Datum „Zuletzt aktualisiert" oben auf dieser Seite wird alle Änderungen widerspiegeln. Die weitere Nutzung der Erweiterung nach Änderungen gilt als Zustimmung zur aktualisierten Richtlinie.</p>

      <div class="contact">
        <strong>Kontakt</strong><br>
        Bei Fragen zu dieser Datenschutzerklärung kontaktieren Sie uns bitte unter <a href="mailto:info@hellootel.app">info@hellootel.app</a>.
      </div>
    </section>

    <!-- ═══════════════ TÜRKÇE ═══════════════ -->
    <section class="lang-section" id="lang-tr">
      <h1>Gizlilik Politikası</h1>
      <p class="updated">Son güncelleme: {{ date('d.m.Y') }}</p>
      <p>Bu Gizlilik Politikası, <strong>Booking Saver for HelloOtel</strong> Chrome uzantısının ("Uzantı") kullanım sırasında hangi bilgileri topladığını, kullandığını ve sakladığını açıklamaktadır.</p>

      <h2>1. Hangi verileri topluyoruz</h2>
      <p>Uzantı yalnızca otel rezervasyonlarını kaydetmek ve işlemek için gereken verileri toplar:</p>
      <ul>
        <li><strong>Rezervasyon verileri</strong> — otel adı, rezervasyon numarası, giriş ve çıkış tarihleri, misafir sayısı, fiyat ve ilgili alanlar — desteklenen tur operatörü portallarının sayfalarından okunur.</li>
        <li><strong>Giriş bilgileri</strong> — HelloOtel kullanıcı adı ve şifresi yalnızca kimlik doğrulama için kullanılır. Şifreler saklanmaz.</li>
        <li><strong>Kimlik doğrulama jetonu</strong> — tarayıcı oturumları arasında oturumunuzu açık tutmak için cihazınızdaki <code>chrome.storage.local</code> alanında yerel olarak saklanır.</li>
      </ul>
      <p>Uzantı, tarama geçmişini, rezervasyonlarla ilgisi olmayan kişisel verileri veya desteklenen tur operatörü portalları dışındaki sayfalardan herhangi bir bilgiyi toplamaz.</p>

      <h2>2. Verileri nasıl kullanıyoruz</h2>
      <ul>
        <li>Rezervasyon kayıtlarını operatörün <strong>booking-configurator.hellootel.com</strong> adresindeki özel veritabanına kaydetmek için.</li>
        <li>Onaylanan rezervasyonları operatör adına HelloOtel bonus sistemine iletmek için.</li>
        <li>Kaydedilen rezervasyonları Uzantı açılır penceresinde ve rezervasyon yönetim sayfasında görüntülemek için.</li>
      </ul>
      <p>Veriler, yalnızca kullanıcı "HelloOtel'e Gönder" düğmesine açıkça tıkladıktan ve rezervasyonu onayladıktan sonra iletilir. Otomatik veya arka plan veri aktarımı gerçekleştirilmez.</p>

      <h2>3. Veri depolama</h2>
      <ul>
        <li>Kimlik doğrulama jetonunuz yerel olarak <code>chrome.storage.local</code>'da saklanır ve yalnızca API'mize Bearer başlığı olarak iletilir.</li>
        <li>Rezervasyon kayıtları güvenli sunucumuzda saklanır ve yalnızca kuruluşunuzdaki kimliği doğrulanmış ve yetkili operatörler tarafından erişilebilir.</li>
      </ul>

      <h2>4. Kullanılan izinler</h2>
      <ul>
        <li><strong>storage</strong> — oturum jetonunu yerel olarak saklamak için.</li>
        <li><strong>Belirli alan adları için izinler</strong> — uzantının desteklenen rezervasyon sayfalarında çalışmasına ve HelloOtel API'siyle iletişim kurmasına olanak tanır. Diğer web sitelerine erişim verilmez.</li>
      </ul>
      <p>Uzantı, üçüncü taraf sayfaların içeriğini değiştirmez — yalnızca "HelloOtel'e Gönder" düğmesini ekler. Kullanıcının açık bir eylemi olmadan hiçbir veri okunmaz veya gönderilmez.</p>

      <h2>5. Veri güvenliği</h2>
      <p>Uzantı ile sunucumuz arasındaki tüm iletişim HTTPS ile şifrelenmektedir. Rezervasyon verilerine erişim yalnızca geçerli jetona sahip kimliği doğrulanmış kullanıcılarla sınırlıdır.</p>

      <h2>6. Çocukların gizliliği</h2>
      <p>Uzantı, 18 yaş ve üzeri seyahat sektörü profesyonelleri için tasarlanmıştır. 18 yaşın altındaki çocuklara yönelik değildir ve küçüklerden bilerek veri toplamayız.</p>

      <h2>7. Bu politikadaki değişiklikler</h2>
      <p>Bu Gizlilik Politikasını zaman zaman güncelleyebiliriz. Sayfanın üstündeki "Son güncelleme" tarihi değişiklikleri yansıtacaktır. Değişikliklerden sonra Uzantı'yı kullanmaya devam etmek, güncellenmiş politikayı kabul ettiğiniz anlamına gelir.</p>

      <div class="contact">
        <strong>İletişim</strong><br>
        Bu Gizlilik Politikası hakkında sorularınız için lütfen <a href="mailto:info@hellootel.app">info@hellootel.app</a> adresine yazın.
      </div>
    </section>

    <!-- ═══════════════ РУССКИЙ ═══════════════ -->
    <section class="lang-section" id="lang-ru">
      <h1>Политика конфиденциальности</h1>
      <p class="updated">Последнее обновление: {{ date('d.m.Y') }}</p>
      <p>Настоящая Политика конфиденциальности описывает, какие данные собирает, использует и хранит расширение Chrome <strong>Booking Saver for HelloOtel</strong> («Расширение») при его использовании.</p>

      <h2>1. Какие данные мы собираем</h2>
      <p>Расширение собирает только данные, необходимые для сохранения и обработки бронирований отелей:</p>
      <ul>
        <li><strong>Данные о бронировании</strong> — название отеля, номер брони, даты заезда и выезда, количество гостей, цена и связанные поля, считанные со страниц поддерживаемых порталов туроператоров.</li>
        <li><strong>Учётные данные</strong> — логин и пароль HelloOtel, используемые исключительно для аутентификации. Пароли не хранятся.</li>
        <li><strong>Токен аутентификации</strong> — хранится локально в <code>chrome.storage.local</code> на вашем устройстве, чтобы вы оставались авторизованы между сеансами браузера.</li>
      </ul>
      <p>Расширение не собирает историю браузера, персональные данные, не связанные с бронированиями, а также любую информацию со страниц за пределами поддерживаемых порталов туроператоров.</p>

      <h2>2. Как мы используем данные</h2>
      <ul>
        <li>Для сохранения записей о бронированиях в приватной базе данных оператора на <strong>booking-configurator.hellootel.com</strong>.</li>
        <li>Для передачи подтверждённых бронирований в бонусную систему HelloOtel от имени оператора.</li>
        <li>Для отображения сохранённых броней в попапе расширения и на странице управления бронированиями.</li>
      </ul>
      <p>Данные передаются в HelloOtel только после того, как пользователь явно нажимает кнопку «Отправить в HelloOtel» и подтверждает бронирование в диалоге. Никакая автоматическая или фоновая передача данных не производится.</p>

      <h2>3. Хранение данных</h2>
      <ul>
        <li>Токен аутентификации хранится локально в <code>chrome.storage.local</code> и передаётся только в виде Bearer-заголовка в наш API.</li>
        <li>Записи о бронированиях хранятся на нашем защищённом сервере и доступны только аутентифицированным и авторизованным турагентствам вашей организации.</li>
      </ul>

      <h2>4. Используемые разрешения</h2>
      <ul>
        <li><strong>storage</strong> — для локального хранения токена сессии.</li>
        <li><strong>Разрешения на конкретные домены</strong> — позволяют расширению работать на поддерживаемых страницах бронирований и обращаться к API HelloOtel. Доступ к другим сайтам расширению не предоставляется.</li>
      </ul>
      <p>Расширение не изменяет содержимое сторонних сайтов — оно только добавляет кнопку «Отправить в HelloOtel». Никакие данные не считываются и не отправляются без явного действия пользователя.</p>

      <h2>5. Безопасность данных</h2>
      <p>Всё взаимодействие между расширением и нашим сервером зашифровано с использованием HTTPS. Доступ к данным бронирований ограничен аутентифицированными пользователями с действующим токеном.</p>

      <h2>6. Конфиденциальность детей</h2>
      <p>Расширение предназначено для специалистов туристической отрасли с возрастом от 18 лет. Оно не направлено на детей до 18 лет, и мы намеренно не собираем данные несовершеннолетних.</p>

      <h2>7. Изменения политики</h2>
      <p>Мы можем периодически обновлять настоящую Политику конфиденциальности. Дата «Последнее обновление» в начале страницы будет отражать любые изменения. Продолжение использования Расширения после изменений означает принятие обновлённой политики.</p>

      <div class="contact">
        <strong>Контакты</strong><br>
        По вопросам, связанным с настоящей Политикой конфиденциальности, обращайтесь по адресу: <a href="mailto:info@hellootel.app">info@hellootel.app</a>.
      </div>
    </section>

    <!-- ═══════════════ УКРАЇНСЬКА ═══════════════ -->
    <section class="lang-section" id="lang-uk">
      <h1>Політика конфіденційності</h1>
      <p class="updated">Останнє оновлення: {{ date('d.m.Y') }}</p>
      <p>Ця Політика конфіденційності описує, які дані збирає, використовує та зберігає розширення Chrome <strong>Booking Saver for HelloOtel</strong> («Розширення») під час його використання.</p>

      <h2>1. Які дані ми збираємо</h2>
      <p>Розширення збирає лише дані, необхідні для збереження та обробки бронювань готелів:</p>
      <ul>
        <li><strong>Дані бронювання</strong> — назва готелю, номер бронювання, дати заїзду та виїзду, кількість гостей, ціна та пов'язані поля, зчитані зі сторінок підтримуваних порталів туроператорів.</li>
        <li><strong>Облікові дані</strong> — логін і пароль HelloOtel, що використовуються виключно для автентифікації. Паролі не зберігаються.</li>
        <li><strong>Токен автентифікації</strong> — зберігається локально в <code>chrome.storage.local</code> на вашому пристрої, щоб ви залишалися авторизовані між сеансами браузера.</li>
      </ul>
      <p>Розширення не збирає історію перегляду, персональні дані, не пов'язані з бронюваннями, а також будь-яку інформацію зі сторінок за межами підтримуваних порталів туроператорів.</p>

      <h2>2. Як ми використовуємо дані</h2>
      <ul>
        <li>Для збереження записів бронювань у приватній базі даних оператора на <strong>booking-configurator.hellootel.com</strong>.</li>
        <li>Для передачі підтверджених бронювань до бонусної системи HelloOtel від імені оператора.</li>
        <li>Для відображення збережених бронювань у спливаючому вікні розширення та на сторінці керування бронюваннями.</li>
      </ul>
      <p>Дані передаються до HelloOtel лише після того, як користувач явно натискає кнопку «Надіслати до HelloOtel» і підтверджує бронювання в діалозі. Автоматична або фонова передача даних не здійснюється.</p>

      <h2>3. Зберігання даних</h2>
      <ul>
        <li>Токен автентифікації зберігається локально в <code>chrome.storage.local</code> і передається лише як Bearer-заголовок до нашого API.</li>
        <li>Записи бронювань зберігаються на нашому захищеному сервері та доступні лише автентифікованим і авторизованим турагентствам вашої організації.</li>
      </ul>

      <h2>4. Використані дозволи</h2>
      <ul>
        <li><strong>storage</strong> — для локального зберігання токена сесії.</li>
        <li><strong>Дозволи на конкретні домени</strong> — дозволяють розширенню працювати на підтримуваних сторінках бронювань і звертатися до API HelloOtel. Доступ до інших сайтів розширенню не надається.</li>
      </ul>
      <p>Розширення не змінює вміст сторонніх сайтів — воно лише додає кнопку «Надіслати до HelloOtel». Жодні дані не зчитуються і не надсилаються без явної дії користувача.</p>

      <h2>5. Безпека даних</h2>
      <p>Уся взаємодія між розширенням і нашим сервером зашифрована за допомогою HTTPS. Доступ до даних бронювань обмежений автентифікованими користувачами з дійсним токеном.</p>

      <h2>6. Конфіденційність дітей</h2>
      <p>Розширення призначене для фахівців туристичної галузі віком від 18 років. Воно не орієнтоване на дітей до 18 років, і ми свідомо не збираємо дані неповнолітніх.</p>

      <h2>7. Зміни до політики</h2>
      <p>Ми можемо час від часу оновлювати цю Політику конфіденційності. Дата «Останнє оновлення» на початку сторінки відображатиме будь-які зміни. Продовження використання розширення після змін означає прийняття оновленої політики.</p>

      <div class="contact">
        <strong>Контакти</strong><br>
        З питань, пов'язаних із цією Політикою конфіденційності, звертайтесь на адресу: <a href="mailto:info@hellootel.app">info@hellootel.app</a>.
      </div>
    </section>

  </div><!-- .card -->
</div><!-- .wrap -->

<script>
  function switchLang(code) {
    document.querySelectorAll('.lang-section').forEach(s => s.classList.remove('visible'));
    document.querySelectorAll('.lang-bar button').forEach(b => b.classList.remove('active'));
    document.getElementById('lang-' + code).classList.add('visible');
    document.getElementById('btn-' + code).classList.add('active');
  }

  // Auto-detect browser language on first load
  (function () {
    const map = { ru: 'ru', uk: 'uk', de: 'de', tr: 'tr', en: 'en' };
    const lang = (navigator.language || 'en').slice(0, 2).toLowerCase();
    if (map[lang] && lang !== 'en') switchLang(map[lang]);
  })();
</script>
</body>
</html>
