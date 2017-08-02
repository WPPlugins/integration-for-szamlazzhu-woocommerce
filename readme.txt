=== Integration for Szamlazz.hu & WooCommerce ===
Contributors: passatgt
Tags: szamlazz.hu, szamlazz, woocommerce, szamlazo, magyar
Requires at least: 3.5
Tested up to: 4.7.4
Stable tag: 2.1.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Számlázz.hu összeköttetés WooCommerce-hez.

== Description ==

= Funkciók =
*   Manuális számlakészítés - minden rendelésnél a jobb oldalon megjelenik egy új gomb, rákattintáskor elküldi az adatokat számlázz.hu-nak és legenerálja a számlát.
*   Automata számlakészítés - Ha a rendelés állapota átállítódik befejezettre, automatán legenerálja a számlát
*   Díjbekérő készítés - Ha a rendelés állapota átállítódik függőben lévőre, automatán legenerálja a díjbekérő számlát. Lehet kézzel egy-egy rendeléshez külön díjbekérőt is csinálni.
*   Számlaértesítő - A számlákat a szamlazz.hu rendszere automatikusan elküldi emailben a vásárlónak(az email szövege a szamlazz.hu-ra belépve szerkeszthető), a szamlazz.hu-n belépve látható mikor lett elküldve és mikor lett elolvasva
*   Papír és elektronikus számlát is lehet csinálni
*   A generált számlát letölti saját weboldalra is, egy véletlenszerű fájlnéven tárolja a wp-content/uploads/wc_szamlazz mappában, így csak az tudja letölteni, aki ismeri a fájlnevet:)
*   Minden számlakészítésnél létrehoz egy megjegyzést a rendeléshoz, hogy mikor, milyen néven készült el a számla
*   Fizetési határidő és megjegyzés írható a számlákhoz
*   Kuponokkal is működik, a számlán negatív tételként fog megjelenni a végén
*   Szállítást is ráírja a számlára
*   A PDF fájl letölthető egyből a Rendelések oldalról is(táblázat utolsó oszlopa)

= Használat =
Telepítés után a WooCommerce / Beállítások oldalon meg kell adni a szamlazz.hu felhasználónevet és jelszót, illetve az ott található többi beállításokat igény szerint.
Minden rendelésnél jobb oldalon megjelenik egy új doboz, ahol egy gombnyomással létre lehet hozni a számlát. Az Opciók gombbal felül lehet írni a beállításokban megadott értékeket 1-1 számlához.
Ha az automata számlakészítés be van kapcsolva, akkor a rendelés lezárásakor(Teljesített rendelés státuszra állítás) automatikusan létrehozza a számlát a rendszer.
A számlakészítés kikapcsolható 1-1 rendelésnél az Opciók legördülőn belül.
Az elkészült számla a rendelés aloldalán és a rendelés listában az utolsó oszlopban található PDF ikonra kattintva letölthető.

FONTOS: Felelősséget én nem vállalok a készített számlákért, mindenki ellenőrizze le saját magának, hogy minden jól működik e. Sajnos minden esetet nem tudok tesztelni, különböző áfakulcsok, termékvariációk, kuponok stb..., így mindenkéne tesztelje le éles használat előtt, ha valami gond van, jelezze felém és megpróbálom javítani. Ez nem egy hivatalos szamlazz.hu bővítmény!

A plugin egy XML fájlt generál, ezt küldi el a szamlazz.hu-nak, majd az egy pdf-ben visszaküldi az elkészített számlát. Az XML fájl generálás előtt módosítható a `wc_szamlazz_xml` filterrel. Ez minden esetben az éppen aktív téma functions.php fájlban történjen, hogy az esetleges plugin frissítés ne törölje ki a módosításokat! Például:

    <?php
    //Számlanyelv változtatás, napi árfolyam feltüntetés, ország és megjegyzés hozzáadása.
    add_filter('wc_szamlazz_xml','wc_szamlazz_xml_lang',10,2);
    function wc_szamlazz_xml_lang($xml,$order) {

        $exchange_rate = get_transient( 'wc_szamlazz_mnb_arfolyam_kozep' );
        if(!$exchange_rate) {
            $exchange_rate = wp_remote_retrieve_body( wp_remote_get( 'http://api.napiarfolyam.hu?bank=mnb&valuta=eur' ) );
            $napiarfolyam_xml = new SimpleXMLElement($exchange_rate);
            $napiarfolyam_kozep = (Array)$napiarfolyam_xml->deviza->item->kozep;
            $napiarfolyam_kozep = $napiarfolyam_kozep[0];
            set_transient( 'wc_szamlazz_mnb_arfolyam_kozep', $napiarfolyam_kozep, 60*60*12 );
            $exchange_rate = $napiarfolyam_kozep;
        }

        //Árfolyam beállítása
        $xml->fejlec->arfolyam = $exchange_rate;
        $xml->fejlec->szamlaNyelve = 'en';
        $xml->fejlec->arfolyamBank = 'MNB';

        //Ország a település után (nincs külön mező ennek szamlazz.hu API-ban)
        $xml->vevo->telepules = $xml->vevo->telepules.', '.WC()->countries->countries[ $order->billing_country ];

        //Megjegyzés kiegészítés
        $xml->fejlec->megjegyzes .= ' - EU Community sales/The customer is liable to pay VAT';

        return $xml;
    }
    ?>

Lehetőség van sikeres és sikertelen számlakészítés után egyedi funckiók meghívására a bővítmény módosítása nélkül:

    <?php
    add_action('wc_szamlazz_after_invoice_success', 'sikeres_szamlakeszites',10,4);
    function($order, $response, $szamlaszam, $pdf_link) {
      //...
    }

    add_action('wc_szamlazz_after_invoice_error', 'sikeres_szamlakeszites',10,5);
    function($order, $response, $agent_error_code, $agent_error, $agent_body) {
      //...
    }
    ?>

== Installation ==

1. Töltsd le a bővítményt
2. Wordpress-ben bővítmények / új hozzáadása menüben fel kell tölteni
3. WooCommerce / Beállítások / Fizetés alján megjelennek a Számlázz.hu beállítások, ezeket be kell állítani
4. Működik(ha minden jól megy)

== Frequently Asked Questions ==

= Hogyan lehet tesztelni a számlakészítést? =

A számlázz.hu-tól lehet kérni kapcsolat űrlapon keresztül, hogy állítsák át a fiókot Teszt üzemmódba, így lehet próbálgatni a számlakészítést.

= Teszt módban vagyok, de a számlaértesítő nem a vásárló emailcímére megy. =

A számlaértesítő teszt módban nem a vásárló emailcímére érkezik, hanem a számlázz.hu-n használt fiók emailcímére.

== Screenshots ==

1. Beállítások képernyő(WooCommerce / Beállítások)
2. Számlakészítés doboz a rendelés oldalon

== Changelog ==

= 2.1.2 =
* Release bugfix

= 2.1.1 =
* WooCommerce 3.0+ kupon kezelés javítás. Mivel 3.0-tól kezdve már nem külön tételként számolja a kupont, így a számla megjegyzésben lesz ráírva, hogy mennyi volt az összes kedvezmény

= 2.1 =
* A díjbekérő készítés most fizetési módhoz van csatolva, egyszerre többet is ki lehet választani és automatán generálja őket
* Több fizetési módot is ki lehet választani az automata teljesítettnek jelölés opciónál
* Opcionálisan a vásárló Rendeléseim oldalán mutatja a díjbekérő és a számla linkeket minden rendelésnél
* Ki/be lehet kapcsolni az email értesítő küldését
* Ha nincs cURL bekapcolva, kiírja a hibát
* Javítva mégegy WooCommerce 3.0 kompatibilitás probléma

= 2.0 =
* WooCommerce 3.0 kompatibilitás(visszafele kompatibilis, tehát frissíthetsz akkor is, ha még régebbi WooCommerce-et használsz.)
* Sztornózni lehet számlát
* wc_szamlazz_after_invoice_success és wc_szamlazz_after_invoice_error action-nel lehet extra funkciókat futtatni sikeres és sikertelen számlakészítés után. Lásd leírásban segítséget.

= 1.2.2 =
* Akkor is lesz számla, ha a cégnévben & karakter van
* A wc_szamlazz_xml filterrel módosítható az eladó számlaszáma(külföldi rendelénél pl ne az alapértelmezett magyar számlát írja rá, hanem a devizát)

= 1.2.1 =
* 1.2-ben előjött bug lett javítva

= 1.2 =
* Számla teljesítettnek jelölése: manuálisan vagy számlakészítéskor automatán egy kiválasztott fizetési módnál
* Ha szamlazz.hu-n teljesítettnek jelölsz egy számlát, az áruházban is az lesz. (lásd beállítások fülön bővebb infót)

= 1.1.4 =
* Megadható az adószám, 100e ft feletti áfatartalomnál a vásárlás oldalon megjelenik az adószám mező(opcionális) és egy figyelmeztetés, hogy kötelező megadni, ha van.

= 1.1.3 =
* Variációs terméknél a megjegyzésben feltüntetésre kerül a kiválasztott variáció
* Manuális díjbekérő készítésekor jó a letöltés link(eddig frissíteni kellett egyet)
* Ha cégnév meg van adva, nem kerül rá a számlára a rendes név

= 1.1.2 =
* 0 Ft-os terméknél nem dob hibát
* Biztonsági hibajavítás - thx @csurga

= 1.1.1 =
* Teszt fiókkal is működik a számlakészítés

= 1.1 =
* WordPress.org-ra feltöltött plugin első verziója
w
