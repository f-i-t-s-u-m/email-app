<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Mail;
use App\Models\Order;
use App\Models\ShippingDetail;
use App\Models\ShippingService;
use App\Models\Vendor;
use Illuminate\Support\Facades\Auth;
use simplehtmldom\HtmlDocument;


class ParserController extends Controller
{

    public function vendors()
    {
        $parsers = [
            'amazon' => 'parseAmazonEmailV2', 'baslerBeauty' => 'parseBaslerBeautyEmail',
            'bestSecret' => 'parseBestSecretEmail', 'deineGroupon' => 'parseDeineGroupon', 'idealOfSweden' => 'parseIdealOfSweden',
            'lonasha' => 'parseLonashaEmail', 'reno' => 'parseRenoEmail', 'sophieRosenburg' => 'parseSophieRosenburg',
            'westWing' => 'parseWestWingEmail', 'limango' => 'parseLimangoEmail', 'HandM' => 'parseHandMEmail'
        ];
        $emails = [];

        foreach (config('emailproviders') as $key => $items) {
            $parse = explode(',', $items);
            foreach ($parse as $item) {
                if (array_key_exists($key, $parsers)) {
                    $emails[$item] = $parsers[$key];
                };
            }
        }

        return $emails;
    }


    public function storeOrder($orders, $mail, $userId)
    {
        if (isset($orders['status']) && isset($orders['vendor_order_no'])) {
            $tracking_url = [];
            if (isset($orders['tracking_url'])) {
                $tracking_url = ['tracking_url' => $orders['tracking_url']];
            }
            $orderDb = Order::query()->where('vendor_order_no', $orders['vendor_order_no'])
                ->first();
            if ($orderDb) {
                $orderDb->update(array_merge(['delivery_status' => $orders['status']], $tracking_url));
                $orders['time'] = strtotime($orders['time'] ?? $mail->received_date);
                $orders['order_id'] = $orderDb->id;
                $orders['place'] = $orders['place'] ?? $orderDb->address ?? "unknown";
                ShippingDetail::create($orders);
            }

            $mail->update(['sys_read' => true, 'user_read' => true]);

            return (true);
        }
        foreach ($orders['order'] ?? [$orders] as $data) {
            if (isset($data['vendor_order_no'])) {

                $orderData['vendor_id'] = $orders['vendor_id'];
                $orderData['address'] = $data['address'] ?? null;
                $orderData['tracking_url'] = $data['tracking_url'] ?? null;
                $orderData['arriving_date'] = isset($data['arriving_date']) ? strtotime($data['arriving_date']) : null;
                $orderData['vendor_order_no'] = $data['vendor_order_no'];
                $orderData['ordered_date'] = strtotime($data['ordered_date'] ?? $mail->received_date) ?: $mail->received_date;
                $orderData['user_id'] = Auth::id() ?? $userId;
                $orderData['items_total_price'] = trim($data['items_total_price'] ?? null, 'EUR');
                $orderData['total_price'] = trim($data['total_price'] ?? null, 'EUR');
                $orderData['mail_id'] = $mail->id;
                $shipingMethod = $data['shipping_method'] ?? "Unknown";
                $orderData['shipping_service_id'] = ShippingService::firstOrCreate(['name' => $shipingMethod])->id;
                $orderData['shipping_price'] = preg_replace('~\D~', '', $data['shippingCost'] ?? $data['shipping'] ?? null);
                $order = Order::create($orderData);
                if ($order && isset($data['items'])) {

                    foreach ($data['items'] as $item) {

                        if (!isset($item['name'])) {
                            continue;
                        }

                        $itemData = [
                            'order_id' => $order->id,
                            'product' => $item['name'],
                            'quantity' => $item['quantity'] ?? 1,
                            'price' => trim($item['price'], 'EUR'),
                            'amount' => trim($item['price'], 'EUR') //setting same as price temporarily, it should be quantity*price
                        ];

                        Item::create($itemData);
                    }
                    $mail->update(['sys_read' => true, 'user_read' => true]);
                }
            }
        }

        return (true);
    }

    public function mailScraper($userId = null)
    {
        $mails = Mail::all();
        foreach ($mails as $mail) {

            $data = null;
            $args['date'] = $mail->received_date;
            if (array_key_exists($mail->sender, $this->vendors()) && !$mail->sys_read) {
                $data = $this->{$this->vendors()[$mail->sender]}($mail->body);
            }

            if ($data) {
                $this->storeOrder($data, $mail, $userId);
            }
        }
        return;
    }


    public function parseHandMEmail($htmlData)
    {
        $data['vendor_id'] = 1;
        $lookingData = ['vendor_order_no' => 'BESTELLNUMMER', 'order_date' => 'BESTELLDATUM', 'payment_method' => 'ZAHLUNGSMETHODE', 'address' => 'WOHNADRESSE', 'shipping_method' => 'LIEFEROPTION'];
        $lookingInfo = ['items_total_price' => 'GESAMTSUMME:', 'shipping_price' => 'LIEFERUNG:', 'total_price' => 'GESAMTSUMME:'];
        $html = new HtmlDocument();
        $html->load($htmlData);

        $td =  $html->find('td[plaintext^=BESTELLNUMMER]', 0) ?? null;
        foreach ($lookingData as $key => $value) {
            $element = null;
            preg_match_all('/<strong[^>]*>' . $value . '<\/strong>.*?<strong/', $td, $element);
            $data[$key] = ltrim(strip_tags($element[0][0] ?? null), $value);
        }
        if ($data['vendor_order_no'] == null) return;
        $currentTable = $html->find("table[plaintext^=BESTELLÜBERSICHT]", 0);

        foreach ($lookingInfo as $key => $value) {
            $td = $html->find("td[plaintext=" . $value . "]", 0);
            if ($key == 'total_price') {
                $data[$key] = $html->find("td[plaintext=" . $value . "]", 1)->next_sibling()->plaintext;
            } else $data[$key] = $td->next_sibling()->plaintext;
        }

        if ($nextTable = $currentTable->next_sibling()) {
            $i = 0;
            while ($tr = $nextTable->find("tr", $i)) {
                $j = 0;
                $z = 0;
                while ($td = $tr->find('td', $j)) {
                    if ($tr->find('td', $j - 1) == "" && $tr->find('td', $j - 2) == "") $z == 0;
                    if ($j == 2) $data['items'][$i]['name'] = $td->plaintext;
                    else if ($j == 5) $data['items'][$i]['quantity'] = $td->plaintext;
                    else if ($j == 6) $data['items'][$i]['price'] = $td->plaintext;
                    else if ($j == 8) $data['items'][$i]['amount'] = $td->plaintext;
                    $j++;
                    $z++;
                }
                $i++;
            }
        }

        return $data;
    }
    public function parseAmazonEmailV2($htmlData)
    {
        $data['vendor_id'] = 2;
        $lookingData = ['arriving_date' => 'Zustellung:', 'shipping_method' => 'Versandart:', 'address' => 'Die Bestellung geht an:'];
        $html = new HtmlDocument();
        $html->load($htmlData);  // load here from mail
        $criticalInfo = $html->find("table[id$='criticalInfo']");
        foreach ($criticalInfo as $key => $ItemInfo) {
            if ($tracking =  $html->find("a[plaintext=Lieferung verfolgen]", $key)) {
                $data['order'][$key]['tracking_url'] = $tracking->href;
            }

            if ($total_price =  $html->find("strong[plaintext=Gesamtbetrag der Bestellung:]", $key)) {
                $data['order'][$key]['total_price'] = $total_price->next_sibling()->plaintext;
            }

            if ($items_total_price = $html->find("strong[plaintext=Endbetrag inkl. USt.:]", $key)) {
                $data['order'][$key]['items_total_price'] = $items_total_price->parent()->next_sibling()->plaintext;
            }
            $totalOrders = count($criticalInfo);

            $orderDetails = $totalOrders > 1 ? $html->find("table[id$='orderDetails']", $key) : $html->find("table[id$='header']", 0);
            $itemDetails = $html->find("table[id$='itemDetails']", $key);
            $textNodes = $orderDetails->find('text');
            foreach ($textNodes as $textNode) {
                $plaintext = trim($textNode->plaintext);
                if (strpos($plaintext, 'Bestellung #') !== FALSE || strpos($plaintext, 'Bestellnummer') !== FALSE) //finding order number
                {
                    $data['order'][$key]['vendor_order_no'] = $textNode->parent()->find('a')[0]->plaintext;
                }
            }
            if ($address = $ItemInfo->find('span[plaintext^=Die Sendung geht an:]', 0)) {
                $data['order'][$key]['address'] = $address->next_sibling()->next_sibling()->plaintext;
            }
            foreach ($lookingData as $dkey => $list) {
                foreach ($ItemInfo->find('p[plaintext^=' . $list . ']') as $tvalue) {
                    $data['order'][$key][$dkey] =  ltrim($tvalue->plaintext, $list);
                }
            }
            foreach ($itemDetails->find('tr') as $itemsKey => $items) {
                if ($items->find('a', 1)) {
                    $data['order'][$key]['items'][$itemsKey]['photo'] = $items->find('img', 0)->src;
                    $data['order'][$key]['items'][$itemsKey]['name'] = $items->find('a', 1)->plaintext;
                    $data['order'][$key]['items'][$itemsKey]['price'] = $items->find('strong', 0)->plaintext;
                }
            }
        }
        return $data;
    }
    public function parseWestWingEmail($htmlData)
    {
        $data = [];
        $data['vendor_id'] = Vendor::firstOrCreate(['name' => 'westWing'])->id;
        $lookingData = ['vendor_order_no' => 'Bestellnummer:', 'ordered_date' => 'Bestelldatum:', 'payment_method' => 'Zahlungsart', 'address' => 'Lieferadresse', 'total_price' => 'Gesamtsumme inkl. MwSt.', 'shipping_cost' => 'Versandkosten:', 'items_total_price' => 'Zwischensumme*:'];
        $html = new HtmlDocument;
        $html->load($htmlData);
        if ($tracking_url = $html->find("a[plaintext^=Verfolgen]", 0)) {
            $data['status'] = 'On Transit';
            $data['tracking_url'] = $tracking_url->href;

            $infoElements = $html->find("span[plaintext^=Bestellnummer:]", 0);
            $data['vendor_order_no'] = ltrim(explode('<br>', $infoElements->innertext)[0], 'Bestellnummer:');
            $data['time'] = ltrim(explode('<br>', $infoElements->innertext)[1], 'Bestelldatum:');
            return $data;
        }
        foreach ($lookingData as $key => $value) {

            if ($key == 'vendor_order_no' || $key == 'ordered_date') {
                $data[$key] = ltrim($html->find('span[plaintext^=' . $value . ']', 0)->plaintext ?? null, $value);
            } else if ($key == 'address' || $key == 'payment_method') {
                $data[$key] = $html->find('span[plaintext^=' . $value . ']', 0)->parent()->parent()->next_sibling()->plaintext;
            } else {
                $data[$key] = $html->find('span[plaintext^=' . $value . ']', 0)->parent()->next_sibling()->plaintext;
            }

            if ($data['vendor_order_no'] == null) {
                return $data;
            }
        }


        $data['tracking_url'] = $html->find("a[plaintext=Verfolgen]", 0)->href ?? null;

        $countTables = $html->find('span[plaintext^=Zwischensumme*:]', 0)->parent()->parent()->parent()->parent()->parent();

        for ($i = 0; $i < count($countTables->children()) - 1; $i++) {
            $data['items'][$i]['name'] = $countTables->children($i)->find("th span", 0)->plaintext;
            $data['items'][$i]['quantity'] =  ltrim($countTables->children($i)->find("span[plaintext^=Anzahl:]", 0)->plaintext, 'Anzahl:');
            $data['items'][$i]['price'] = $countTables->children($i)->find("th span", 0 - 1)->plaintext;
        }
        return $data;
    }

    public function parseLimangoEmail($htmlData)
    {
        $data['vendor_id'] = Vendor::firstOrCreate(['name' => 'Limango'])->id;
        $lookingData = ['vendor_order_no' => 'Bestellnummer:', 'arriving_date' => 'Voraussichtlicher Liefertermin:', 'payment_method' => 'Zahlungsmethode:', 'address' => 'Lieferadresse:', 'total_price' => 'Warenwert:', 'shipping_cost' => 'Versandkosten:', 'items_total_price' => 'Rechnungsbetrag inkl. MwSt:'];

        $html = new HtmlDocument();
        $html->load($htmlData);
        if ($tracking_url = $html->find("a[plaintext^=ZUR SENDUNGSVERFOLGUNG]", 0)) {

            $data['status'] = 'On Transit';
            $data['tracking_url'] = $tracking_url->href;

            $infoElements = $html->find("span[plaintext^=Bestellnummer:]", 0);
            $data['vendor_order_no'] = $infoElements->parent()->next_sibling()->plaintext;
            return $data;
        }
        foreach ($lookingData as $lkey => $ldata) {
            $data[$lkey] = $html->find("font[plaintext^=$ldata]", 0)->parent()->parent()->next_sibling()->plaintext;
        }
        $datatable =  $html->find('th[plaintext=ARTIKEL]', 0)->parent()->parent()->parent()->parent()->parent()->parent()->parent()->parent()->parent()->parent()->parent()->next_sibling();
        $itemsRow =  $datatable->children(0)->children();
        foreach ($itemsRow as $ikey => $ivalue) {
            foreach ($ivalue->find('text') as $i => $ivalue) {
                if ($i == 0) $data['items'][$ikey]['name'] =  $ivalue->plaintext;
                else if ($i == 1) $data['items'][$ikey]['quantity'] =  $ivalue->plaintext;
                else if ($i == 2) $data['items'][$ikey]['price'] =  $ivalue->plaintext;
                else if ($i == 3) $data['items'][$ikey]['total_price'] =  $ivalue->plaintext;
            }
        }
        return $data;
    }

    public function parseSophieRosenburg($htmlData)
    {

        $data = [];
        $html = new HtmlDocument();
        $html->load($htmlData);
        $title = $html->find("div .ha", 0)->plaintext ?? "null";
        $lookingData = ['item_total_price' => 'Zwischensumme:', 'discount' => 'Rabatt:', 'delivery_method' => 'Lieferung:', 'payment_method' => 'Zahlungsmethode:', 'total_price' => 'Gesamt:'];
        //Set title
        $data["title"] = $title;
        //Set vendor
        $data['vendor'] = "Sophie Rosenburg";

        //Get order number
        $orderNumberElements = $html->find('h2[plaintext^="Bestellnummer #"]', 0);
        $orderElements =  explode("(", trim($orderNumberElements->innertext, 'Bestellnummer #'));
        $data['vendor_order_no'] = $orderElements[0];
        $data['ordered_date'] = trim($orderElements[1], ')');

        $data['address'] = $html->find('table[id$=addresses]', 0)->find('address', 1)->plaintext;
        $itemsTable = $html->find('div[id$=body_content_inner] div table', 0);
        foreach ($lookingData as $ldkey => $ldvalue) {
            $data[$ldkey] =  explode("(", ltrim($itemsTable->find('tfoot tr[plaintext^=' . $ldvalue . ']', 0)->plaintext, $ldvalue))[0];
        }
        foreach ($itemsTable->find('tbody tr') as $ikey => $items) {
            $data['items'][$ikey]['name'] = $items->find('td', 0)->plaintext;
            $data['items'][$ikey]['quantity'] = $items->find('td', 1)->plaintext;
            $data['items'][$ikey]['price'] = $items->find('td', 2)->plaintext;
        }

        return $data;
    }

    public function parseBaslerBeautyEmail($htmlData)
    {
        // used sample emails 
        //Versandbestätigung Ihre Bestellung ist auf dem Weg.html
        //Wir haben Ihre Bestellung erhalten (3206230).html
        $html = new HtmlDocument();
        $html->load($htmlData);

        $data = [];
        $orders = [];
        $orderNumber = '';

        //Mail title
        $title = $html->find("div .ha", 0)->plaintext;

        //Get order number
        if ($orderNumberElements = $html->find("*[plaintext^=Auftragsnummer]", 0)) {
            $orderNumber = ltrim($orderNumberElements->plaintext, 'Auftragsnummer:');
        }

        if ($tracking_url = $html->find('p[plaintext^=Ihr Link zur Sendungsverfolgung:]', 0)) {
            $data['tracking_ur'] = $tracking_url->find('a', 0)->href;
            $data['vendor_order_no'] = $orderNumber;
            $data['status'] = 'On Transit';
            return $data;
        }

        if ($orderNumber !== '') {
            //Set title
            $data["title"] = $title;
            //Set vendor
            $data["vendor_id"] = 8;
            //Set order Number
            $data['vendor_order_no'] = $orderNumber;
            $orderData = $html->find("table tbody tr[valign='top'] td img.CToWUd");



            //Get payment method
            $paymentMethodsElements = $html->find("span b");
            foreach ($paymentMethodsElements as $paymentMethodsElement) {
                if (strpos($paymentMethodsElement, "Bezahlart:") !== false) {
                    $paymentMethodData = strip_tags($paymentMethodsElement->parent());
                    $paymentMethod = str_replace("Bezahlart:", "", $paymentMethodData);
                }
            }
            $data['paymentMethod'] = $paymentMethod ?? null;


            //Get shipment method
            $shipmentMethodsElements = $html->find("span b");
            foreach ($shipmentMethodsElements as $shipmentMethodsElement) {
                if (strpos($shipmentMethodsElement, "Der Versand erfolgt mit:") !== false) {
                    $shipmentMethodData = strip_tags($shipmentMethodsElement->parent());
                    $shipmentMethod = str_replace("Der Versand erfolgt mit:", "", $shipmentMethodData);
                }
            }

            //Set payment method
            $data['shipmentMethod'] = $shipmentMethod ?? null;


            //Get Billing address
            $billingAddressElements = $html->find("h4");
            foreach ($billingAddressElements as $billingAddressElement) {
                if (strpos($billingAddressElement, "Rechnungsadresse") !== false) {
                    $billingMethodData = strip_tags($billingAddressElement->parent());
                    $billingAddress = str_replace("Rechnungsadresse", "", $billingMethodData);
                }
            }

            //Set billing address
            $data['address'] = $billingAddress ?? null;


            //Get subtotal
            $subTotalElements = $html->find("td p");
            foreach ($subTotalElements as $subTotalElement) {
                if (strpos($subTotalElement, "Zwischensumme") !== false) {
                    $subTotalData = strip_tags($subTotalElement->parent()->parent()->plaintext);
                    $subTotal = str_replace("Zwischensumme", "", $subTotalData);
                }
            }

            //Set subTotal
            $data['items_total_price'] = $subTotal ?? null;

            $TotalElements = $html->find("td p");
            foreach ($TotalElements as $TotalElement) {
                if (strpos($TotalElement, "Gesamtbetrag:") !== false) {
                    $TotalData = strip_tags($TotalElement->parent()->parent()->plaintext);
                    $Total = str_replace("Gesamtbetrag:", "", $TotalData);
                }
            }
            //Set subTotal
            $data['total_price'] = $Total ?? null;

            //Check if there are orders
            if (count($orderData) > 0) {
                foreach ($orderData as $order) {
                    $orderRecord = $order->parent()->parent();
                    $itemName = strip_tags($orderRecord->find("td p", 0));
                    $quantity = $orderRecord->find("td p b", 0)->plaintext;
                    $total = $orderRecord->find("td p b", 1)->plaintext;
                    if ($itemName == 'Servicepauschale') continue;
                    $orderInfo = [
                        "name" => $itemName,
                        "quantity" => $quantity,
                        "price" => $total,
                    ];
                    array_push($orders, $orderInfo);
                }
            }

            //Set orders
            $data['items'] = $orders;
        }
        return $data;
    }

    public function parseBestSecretEmail($htmlData)
    {

        // used sample emails 
        //Vielen Dank für Ihre Bestellung.html

        $html = new HtmlDocument();
        $html->load($htmlData);
        //Variables
        $data["vendor_id"] = Vendor::firstOrCreate(['name' => 'BestSecret'])->id;
        $title = $html->find("div .ha", 0)->plaintext;
        $orderDate = '';
        $orderNumber = '';
        $customerNumber = '';
        $billingAddress = '';
        $deliveryAddress = '';
        $paymentMethod = '';
        $orders = [];
        $shippingCost = '';
        $totalCost = '';

        //Set title
        $data['title'] = $title;
        //Set vendor

        //Get order date
        $orderDateElements = $html->find("font");
        foreach ($orderDateElements as $orderDateElement) {
            if (strpos($orderDateElement, "Auftragsdatum:") !== false) {
                $orderDate = $orderDateElement->parent()->next_sibling()->find('font', 0)->plaintext;
            }
        }

        //Set order date
        $data['ordered_date'] = $orderDate;


        //Get order number
        $orderNumberElements = $html->find("font");
        foreach ($orderNumberElements as $orderNumberElement) {
            if (strpos($orderNumberElement, "Bestellnummer:") !== false) {
                $orderNumber = $orderNumberElement->parent()->next_sibling()->find('font', 0)->plaintext;
            }
        }


        //Set order number
        $data['vendor_order_no'] = $orderNumber;


        //Get customer number
        $customerNumberElements = $html->find("font");
        foreach ($customerNumberElements as $customerNumberElement) {
            if (strpos($customerNumberElement, "Kundennummer:") !== false) {
                $customerNumber = str_replace("Zwischensumme", "", $customerNumberElement->parent()->next_sibling()->find('font', 0)->plaintext);
            }
        }

        //Set customer number
        $data['customerNumber'] = $customerNumber;



        //Get billing address
        $billingAddressElements = $html->find("font i");
        foreach ($billingAddressElements as $billingAddressElement) {
            if (strpos($billingAddressElement, "Ihre Rechnungsadresse:") !== false) {
                $billingAddress = strip_tags($billingAddressElement->parent()->parent()->find('font', 2));
            }
        }

        //Set billing address
        $data['billing_address'] = ltrim($billingAddress, 'Melanie Brenner');


        //Get delivery address
        $deliveryAddressElements = $html->find("font i");
        foreach ($deliveryAddressElements as $deliveryAddressElement) {
            if (strpos($deliveryAddressElement, "Ihre Lieferadresse:") !== false) {
                $deliveryAddress = strip_tags($deliveryAddressElement->parent()->parent()->find('font', 2));
            }
        }

        //Set delivery address
        $data['address'] = ltrim($deliveryAddress, 'Melanie Brenner');


        //Get get payment method
        $paymentMethodElements = $html->find("font i");
        foreach ($paymentMethodElements as $paymentMethodElement) {
            if (strpos($paymentMethodElement, "Ihre Zahlungsart:") !== false) {
                $paymentMethod = strip_tags($paymentMethodElement->parent()->parent()->find('font', 2));
            }
        }

        //Set payment method
        $data['payment_method'] = ltrim($paymentMethod, 'Melanie Brenner');


        //Get order details
        $orderDetailsElements = $html->find("font");
        foreach ($orderDetailsElements as $orderDetailsElement) {
            if (strpos($orderDetailsElement, "Ihre Bestellung:") !== false) {
                $ordersData = $orderDetailsElement->parent()->find("tr");
                foreach ($ordersData as $ord) {
                    if (strpos($ord, "<td valign=\"top\">") !== false) {
                        $quantity = $ord->find("td", 1)->plaintext;
                        $product = strip_tags($ord->find("td", 2));
                        $price = $ord->find("td", 3)->plaintext;

                        $orderDetails = [
                            'name' => $product,
                            'quantity' => trim($quantity, ' Stk.'),
                            'price' => $price
                        ];

                        array_push($orders, $orderDetails);
                    }
                }
            }
        }

        //Set orders
        $data['items'] = $orders;


        //Get get shipping cost
        $shippingCostElements = $html->find("font");
        foreach ($shippingCostElements as $shippingCostElement) {
            if (strpos($shippingCostElement, "Versandkosten:") !== false) {
                $shippingCost = $shippingCostElement->parent()->next_sibling()->plaintext;
            }
        }

        //Set shipping cost
        $data['shipping_price'] = $shippingCost;

        //Get total
        $totalCostElements = $html->find("font");
        foreach ($totalCostElements as $totalCostElement) {
            if (strpos($totalCostElement, "zu zahlender Betrag:") !== false) {
                $totalCost = $totalCostElement->parent()->next_sibling()->plaintext;
            }
        }

        //Set total cost
        $data['total_price'] = $totalCost;
        return $data;
    }

    public function parseDeineGroupon($htmlData)
    {
        // sample test from
        // Deine Groupon-Bestellbestätigung.html

        $data = [];
        $orders = [];
        $orderNumber = '';
        $total = '';
        $subTotal = '';
        $discount = '';
        $html = new HtmlDocument();

        $html->load($htmlData);
        //Mail title
        $title = $html->find("div .ha", 0)->plaintext;

        //Set title
        $data["title"] = $title;
        //Set vendor
        $data['vendor_id'] = Vendor::firstOrCreate(['name' => 'Groupon'])->id;

        //Get orderNumber
        $orderNumberElements = $html->find("td");
        foreach ($orderNumberElements as $orderNumberElement) {
            if (strpos($orderNumberElement, "Deine Bestellnummer lautet") !== false) {
                $orderNumber = $orderNumberElement->find("strong", 0)->plaintext;
            }
        }

        //Set order number
        $data['vendor_order_no'] = $orderNumber;

        //Get sub total
        $subTotalElements = $html->find("td");
        foreach ($subTotalElements as $subTotalElement) {
            if (strpos($subTotalElement, "Summe:") !== false) {
                $subTotal = strip_tags($subTotalElement->next_sibling());
            }
        }

        //Set sub total
        $data['items_total_price'] = $subTotal;


        //Get discount
        $discountElements = $html->find("td");
        foreach ($discountElements as $discountElement) {
            if (strpos($discountElement, "Rabatt:") !== false) {
                $discount = strip_tags($discountElement->next_sibling());
            }
        }

        //Set discount
        $data['discount'] = $discount;


        //Get total
        $totalElements = $html->find("td");
        foreach ($totalElements as $totalElement) {
            if (strpos($totalElement, "Gesamtsumme:") !== false) {
                $total = strip_tags($totalElement->next_sibling());
            }
        }

        //Set total
        $data['total_price'] = $total;

        //Get orders
        $ordersElements = $html->find("table");
        foreach ($ordersElements as $ordersElement) {
            if (strpos($ordersElement, "Beschreibung") !== false) {
                $ordersTable = $ordersElement->find("table table table table table tr tr", 0);

                $product = $ordersTable->find("table a", 1)->plaintext ?? '';
                $quantity = $ordersTable->find('td', 4)->plaintext ?? '';
                $amount = $ordersTable->find('td', 5)->plaintext ?? '';

                $orderDetails = [
                    "name" => $product,
                    "quantity" => $quantity,
                    "price" => $amount
                ];

                if ($orderDetails['name'] !== '') {
                    array_push($orders, $orderDetails);
                }
            }
        }


        //Set orders
        $data['items'] = $orders;
        return $data;
    }

    public function parseAmazonEmail($htmlData, $args = null)
    {
        $orders = []; // One email can have multiple orders

        $data = [
            'vendor_id' => 2,
            'vendor_order_no' => '',
            'address' => '',
            'arriving_date' => null,
            'ordered_date' => null,
            'items_total_price' => '',
            'total_price' => '',
            'items' => [],
        ];

        $html = new HtmlDocument();

        $html->load($htmlData);

        $textNodes = $html->find('text');
        foreach ($textNodes as $textNode) {
            $plaintext = trim($textNode->plaintext);

            if (strpos($plaintext, 'Bestellung #') !== FALSE) //finding order number
            {
                $data['vendor_order_no'] = $textNode->parent()->find('a')[0]->plaintext;
            }

            if (strpos($plaintext, 'Die Bestellung geht an:') !== FALSE) //finding order address
            {
                $data['address'] = explode(':', $textNode->plaintext)[1];
            }

            if (strpos($plaintext, 'Zustellung:') !== FALSE) //finding arriving date
            {
                if ($textNode->find('b')) {
                    $data['arriving_date'] = $textNode->find('b')[0]->plaintext;
                } else {
                    $data['arriving_date'] = $textNode->parent()->find('b')[0]->plaintext;
                }
            }

            if (strpos($plaintext, 'Endbetrag inkl. USt.:') !== FALSE) //finding total amount
            {
                // $data['items_total_price'] = $textNode->parent()->next_sibling()->plaintext;
                $data['total_price'] = $textNode->parent()->next_sibling()->plaintext ?? ""; //This may include tax
            }
        }

        $items = [];
        if ($data['vendor_order_no'] != '') {
            $allTables = $html->find('table');
            foreach ($allTables as $key => $table) {
                if (strpos($table->id, 'itemDetails') !== FALSE) { //If table id has string 'itemDetails', it has items.
                    $itemNodes = $table->find('tr');
                    foreach ($itemNodes as $itemKey => $itemNode) { //Looping through each items

                        $itemDetails = $itemNode->find('td');

                        // dump(count($itemDetails));

                        foreach ($itemDetails as $itemDetail) { //Looping through item details like price, name etc
                            if (strpos($itemDetail->class, 'name') !== FALSE) {
                                $items[$itemKey]['name'] = $itemDetail->find('a')[0]->plaintext;
                            }

                            if (strpos($itemDetail->class, 'price') !== FALSE) {
                                $items[$itemKey]['price'] = $itemDetail->find('strong')[0]->plaintext;
                            }

                            $items[$itemKey]['quantity'] = 1; //Setting 1 temporarily
                        }
                    }
                }
            }
        }

        $data['items'] = $items;
        $data['ordered_date'] = $data['ordered_date'] ?? $args['date'] ?? null;

        $orders[] = $data; // For now its handling single order from an email.

        return $orders;
    }

    public function parseIdealOfSweden($htmlData)
    {
        //used sample emails
        //Thank you for your order!.html
        $data = [];
        $orders = [];
        $orderNumber = '';
        $total = '';
        $subTotal = '';
        $discount = '';
        $shipping = '';
        $shippingAddress = '';
        $billingAddress = '';

        $html = new HtmlDocument();
        $html->load($htmlData);
        //Mail title
        $title = $html->find("div .ha", 0)->plaintext;

        //Set title
        $data["title"] = $title;
        //Set vendor
        $data['vendor_id'] = 4;

        //Get orderNumber
        $orderNumberElements = $html->find("p");
        foreach ($orderNumberElements as $orderNumberElement) {
            if (strpos($orderNumberElement, "Ihre Bestellnummer ist:") !== false) {
                $orderNumberData = str_replace("Ihre Bestellnummer ist:", "", strip_tags($orderNumberElement));
                $orderNumber = str_replace("BESTELLBESTÄTIGUNG#", "", $orderNumberData);
            }
        }

        //Set order number
        $data['vendor_order_no'] = $orderNumber;


        //Get sub total
        $subTotalElements = $html->find("h3");
        foreach ($subTotalElements as $subTotalElement) {
            if (strpos($subTotalElement, "SUMME") !== false) {
                $subTotal = strip_tags($subTotalElement->parent()->next_sibling());
            }
        }

        //Set sub total
        $data['items_total_price'] = $subTotal;

        //Get discount
        $discountElements = $html->find("h3");
        foreach ($discountElements as $discountElement) {
            if (strpos($discountElement, "Discount code") !== false) {
                $discount = strip_tags($discountElement->parent()->next_sibling());
            }
        }

        //Set discount
        $data['discount'] = $discount;


        //Get shipping
        $shippingElements = $html->find("h3");
        foreach ($shippingElements as $shippingElement) {
            if (strpos($shippingElement, "VERSAND") !== false) {
                $shipping = strip_tags($shippingElement->parent()->next_sibling());
            }
        }


        //Set shipping
        $data['shipping_price'] = $shipping;

        //Get total
        $totalElements = $html->find("h3");
        foreach ($totalElements as $totalElement) {
            if (strpos($totalElement, "GESAMTBETRAG") !== false) {
                $total = strip_tags($totalElement->parent()->next_sibling());
            }
        }

        //Set total
        $data['total_price'] = $total;

        //Get shipping address
        $shippingAddressElements = $html->find("h1");
        foreach ($shippingAddressElements as $shippingAddressElement) {
            if (strpos($shippingAddressElement, "LIEFERADRESSE") !== false) {
                $shippingAddress = strip_tags(
                    $shippingAddressElement->parent()->parent()->next_sibling()->next_sibling()
                );
            }
        }


        //Set shipping address
        $data['shippingAddress'] = $shippingAddress;


        //Get billing address
        $billingAddressElements = $html->find("h1");
        foreach ($billingAddressElements as $billingAddressElement) {
            if (strpos($billingAddressElement, "RECHNUNGSADRESSE") !== false) {
                $billingAddress = strip_tags(
                    $billingAddressElement->parent()->parent()->next_sibling()->next_sibling()
                );
            }
        }

        //Set billing address
        $data['address'] = $billingAddress;

        //Get orders
        $orderElements = $html->find("table tr th h2");
        foreach ($orderElements as $orderElement) {
            if (strpos($orderElement, "PRODUKT") !== false) {
                $orderDetails = $orderElement->parent()->parent()->parent()->parent()->find('tbody tr[valign="top"]');
                foreach ($orderDetails as $orderData) {
                    $product = $orderData->find("td h3", 0)->plaintext;
                    $quantity = $orderData->find("td span", 2)->plaintext;
                    $amount = $orderData->find("td span", -1)->plaintext;

                    $orderInfo = [
                        "name" => $product,
                        "quantity" => $quantity,
                        "price" => $amount
                    ];

                    if ($orderInfo['name'] != '') {
                        array_push($orders, $orderInfo);
                    }
                }
            }
        }


        //Set orders
        $data['items'] = $orders;
        return $data;
    }

    public function parseLonashaEmail($htmlData)
    {
        //test sample email
        //Bestellung #12919 bestätigt.html

        $data = [];
        $orders = [];
        $orderNumber = '';
        $total = '';
        $subTotal = '';
        $shipping = '';
        $shippingAddress = '';
        $billingAddress = '';
        $shippingDetails = '';
        $paymentDetails = '';

        $html = new HtmlDocument();
        $html->load($htmlData);
        //Mail title
        $title = $html->find("div .ha", 0)->plaintext;

        //Set title
        $data["title"] = $title;
        //Set vendor
        $data['vendor_id'] = 11;


        //Get order number
        $orderNumberElements = $html->find("span");
        foreach ($orderNumberElements as $orderNumberElement) {
            if (strpos($orderNumberElement, "Bestellung") !== false) {
                $orderNumber = str_replace("Bestellung #", "", $orderNumberElement->plaintext);
            }
        }

        //Set order number
        $data['vendor_order_no'] = $orderNumber;


        //Get sub total
        $subTotalElements = $html->find("span");
        foreach ($subTotalElements as $subTotalElement) {
            if (strpos($subTotalElement, "Zwischensumme") !== false) {
                $subTotal = $subTotalElement->parent()->parent()->next_sibling()->plaintext;
            }
        }


        //Set sub total
        $data['items_total_price'] = $subTotal;

        //Get shipping
        $shippingElements = $html->find("span");
        foreach ($shippingElements as $shippingElement) {
            if (strpos($shippingElement, "Versand") !== false) {
                $shipping = $shippingElement->parent()->parent()->next_sibling()->plaintext;
            }
        }

        //Set shipping
        $data['shipping_price'] = $shipping;


        //Get total
        $totalElements = $html->find("span");
        foreach ($totalElements as $totalElement) {
            if (strpos($totalElement, "Gesamt") !== false) {
                $total = $totalElement->parent()->parent()->next_sibling()->plaintext;
            }
        }

        //Set total
        $data['total_price'] = $total;


        //Get shipping address
        $shippingElements = $html->find("h4");
        foreach ($shippingElements as $shippingElement) {
            if (strpos($shippingElement, "Lieferadresse") !== false) {
                $shippingAddress = strip_tags($shippingElement->next_sibling());
            }
        }

        //Set shipping address
        $data['shippingAddress'] = $shippingAddress;


        //Get billing address
        $billingAddressElements = $html->find("h4");
        foreach ($billingAddressElements as $billingAddressElement) {
            if (strpos($billingAddressElement, "Rechnungsadresse") !== false) {
                $billingAddress = strip_tags($billingAddressElement->next_sibling());
            }
        }

        //Set billing address
        $data['address'] = $billingAddress;

        //Get shipping details
        $shippingDetailsElements = $html->find("h4");
        foreach ($shippingDetailsElements as $shippingDetailsElement) {
            if (strpos($shippingDetailsElement, "Versand") !== false) {
                $shippingDetails = $shippingDetailsElement->next_sibling()->plaintext;
            }
        }

        //Set shipping details
        $data['shippingDetails'] = $shippingDetails;

        //Get payment details
        $paymentDetailsElements = $html->find("h4");
        foreach ($paymentDetailsElements as $paymentDetailsElement) {
            if (strpos($paymentDetailsElement, "Zahlung") !== false) {
                $paymentDetails = strip_tags($paymentDetailsElement->next_sibling());
            }
        }
        //Set payment details
        $data['payment_method'] = explode('—', $paymentDetails)[0];


        //Get order details
        $orderDetailsElements = $html->find("h3");
        foreach ($orderDetailsElements as $orderDetailsElement) {
            if (strpos($orderDetailsElement, "BestellÃ¼bersicht") !== false || strpos($orderDetailsElement, "Bestellübersicht") !== FALSE) {
                $orderDetails = $orderDetailsElement->parent()->parent()->parent()->parent()->next_sibling()->find('table', 0)->find("table tbody tr", 0);
                $productElement = $orderDetails->find("td span", 0)->plaintext;
                $amount = $orderDetails->find("td p", 0)->plaintext;
                $product = explode('×', $productElement);
                $orderInfo = [
                    "name" => $product[0],
                    "quantity" => $product[1] ?? 1,
                    "price" => $amount
                ];

                if ($orderInfo['name'] !== '') {
                    array_push($orders, $orderInfo);
                }
            }
        }

        //Set orders
        $data['items'] = $orders;
        return $data;
    }

    public function parseRenoEmail($htmlData)
    {
        //used sample email
        //Deine Bestellung Nr. 2008169161 ist bei uns eingegangen.html
        //Deine RENO Bestellung 2008169161 ist auf dem Weg zu Dir.html

        $data = [];
        $orderNumber = '';
        $orderDate = '';
        $shippingAddress = '';
        $shippingMethod = '';
        $billingAddress = '';
        $paymentMethod = '';
        $shippingCost = '';
        $total = '';
        $orders = [];
        $html = new HtmlDocument();
        $html->load($htmlData);
        //Mail title
        $title = $html->find("div .ha", 0)->plaintext;
        if (strpos($title, 'Deine RENO Bestellung') !== FALSE) {
            $data["tracking_url"] = $html->find("a[plaintext=Hier]", 0)->href;
            $data['vendor_order_no'] = explode(' ', explode('Bestellung ', $title)[1] ?? "")[0];
            $data['status'] = 'On Transit';
            return $data;
        }
        //Get order number
        $orderNumberElements = $html->find("strong");
        foreach ($orderNumberElements as $orderNumberElement) {
            if (strpos($orderNumberElement, "Bestellnummer:") !== false) {
                $orderNumber = ltrim($orderNumberElement->plaintext, "Bestellnummer:");
            }
        }

        //Check if email has orders
        if ($orderNumber !== '') {
            //Set title
            $data["title"] = $title;
            //Set vendor
            $data['vendor_id'] = 6;
            //Set order number
            $data['vendor_order_no'] = $orderNumber;

            //Set order date
            $orderDateElements = $html->find("strong");
            foreach ($orderDateElements as $orderDateElement) {
                if (strpos($orderDateElement, "Bestelldatum:") !== false) {
                    $orderDate = str_replace("Bestelldatum:", "", $orderDateElement->plaintext);
                }
            }

            //Set order date
            $data['ordered_date'] = $orderDate;

            //Get shipping address
            $shippingAddressElements = $html->find("strong");
            foreach ($shippingAddressElements as $shippingAddressElement) {
                if (strpos($shippingAddressElement, "Lieferadresse:") !== false) {
                    $shippingAddress = strip_tags($shippingAddressElement->parent()->parent()->next_sibling());
                }
            }

            //Set shipping address
            $data['shippingAddress'] = $shippingAddress;


            //Get shipping method
            $shippingMethodElements = $html->find("strong");
            foreach ($shippingMethodElements as $shippingMethodElement) {
                if (strpos($shippingMethodElement, "Versandmethode:") !== false) {
                    $shippingMethodData = strip_tags($shippingMethodElement->parent()->parent());
                    $shippingMethod = str_replace("Versandmethode:", "", $shippingMethodData);
                }
            }

            //Set shipping method
            $data['shipping_method'] = $shippingMethod;


            //Get billing address
            $billingAddressElements = $html->find("strong");
            foreach ($billingAddressElements as $billingAddressElement) {
                if (strpos($billingAddressElement, "Rechnungsadresse:") !== false) {
                    $billingAddress = explode('<a', $billingAddressElement->parent()->parent()->next_sibling());
                }
            }

            //Set billing address
            $data['address'] = strip_tags($billingAddress[0]);


            //Get payment method
            $paymentMethodElements = $html->find("strong");
            foreach ($paymentMethodElements as $paymentMethodElement) {
                if (strpos($paymentMethodElement, "Zahlungsmethode:") !== false) {
                    $paymentMethod = $paymentMethodElement->parent()->parent()->parent()->next_sibling();
                }
            }

            //Set payment method
            $data['payment_method'] = strip_tags(explode('<br>', $paymentMethod)[0]);



            //Get payment method
            $shippingCostElements = $html->find("td");
            foreach ($shippingCostElements as $shippingCostElement) {
                if (strpos($shippingCostElement, "Versandkosten:") !== false) {
                    $shippingCost = strip_tags($shippingCostElement->next_sibling());
                }
            }

            //Set shipping cost
            $data['shipping_price'] = $shippingCost;


            //Get total
            $totalElements = $html->find("strong");
            foreach ($totalElements as $totalElement) {
                if (strpos($totalElement, "Gesamtsumme (inkl. MwSt.):") !== false) {
                    $total = strip_tags($totalElement->parent()->next_sibling());
                }
            }

            //Set total
            $data['total_price'] = $total;


            //Get order details
            $orderDetailsElements = $html->find("th");
            foreach ($orderDetailsElements as $orderDetailsElement) {
                if (strpos($orderDetailsElement, "Produkt") !== false) {
                    $orderDetails = $orderDetailsElement->parent()->next_sibling();
                    $product = $orderDetails->find("td", 0)->plaintext;
                    $quantity = $orderDetails->find("td", 1)->plaintext;
                    $price = $orderDetails->find("td", 2)->plaintext;
                    $amount = $orderDetails->find("td", 3)->plaintext;

                    $orderInfo = [
                        'name' => $product,
                        'quantity' => $quantity,
                        'price' => $price,
                        'amount' => $amount
                    ];


                    if ($orderInfo['name'] !== '') {
                        array_push($orders, $orderInfo);
                    }
                }
            }


            //Set orders
            $data['items'] = $orders;
        }
        return $data;
    }

    public function parseEbayEmail($htmlData)
    {
        //used sample email
        // BESTELLUNG ZUGESTELLT Apple TV (4. Generation) 64GB.html
        //1 KAUF BESTÄTIGT Teppich Läufer 100 x.html
        //1 Wir haben Ihre Bestellung erhalten!.html

        $html = new HtmlDocument();
        $html->load($htmlData);

        //Variables
        $data = [];
        $title = $html->find("div .ha", 0)->plaintext;
        $orderNumber = '';
        $total = '';
        $total1 = '';


        //Set title
        $data['title'] = $title;

        //Set vendor
        $data['vendor_id'] = 5;
        //Get get order number
        $orderNumberElements = $html->find("p");
        foreach ($orderNumberElements as $orderNumberElement) {
            if (strpos($orderNumberElement, "Bestellnummer") !== false) {
                $orderNumber = str_replace("Bestellnummer:", "", $orderNumberElement->plaintext);
            }
        }

        $status = $html->find("span[plaintext$=Ihre Bestellung wurde zugestellt!]", 0);
        $info = $html->find("tr[plaintext$=Zugestellt:]", 0);
        if ($status && $info) {
            $data['vendor_order_id'] = ltrim($html->find("font[plaintext^=Bestellnummer: ]", 0)->plaintext, 'Bestellnummer: ');
            $data['status'] = 'Delivered';
            $data['tracking_url'] = $info->parent()->last_child()->find('a', 0)->href;
        }

        //Check if order number is set

        else if ($orderNumber != '') {
            //Set order number
            $data['vendor_order_no'] = $orderNumber;

            //Get total
            $totalElements = $html->find("p");
            foreach ($totalElements as $totalElement) {
                if (strpos($totalElement, "Insgesamt") !== false) {
                    $total = str_replace("Insgesamt:", "", $totalElement->plaintext);
                }
            }

            //get Address

            $addressElement = $html->find("tr[plaintext$=Versandziel:]", 0) ??
                $html->find("tr[plaintext$=Ihre Bestellung wird verschickt an:]", 0);

            $data['address'] = $addressElement ? $addressElement->next_sibling()->plaintext : "unknown";


            $totalprice = $html->find("td[plaintext$=Preis]", 0);
            $data['total_price'] = $totalprice ? $totalprice->next_sibling()->plaintext : "";

            $payment_method = $html->find("td[plaintext^=Gesamtbetrag wird abgebucht von]", 0);
            $data["payment_method"] = $payment_method ? $payment_method->children(0)->alt : "";


            //Get total
            $totalElements1 = $html->find("p");
            foreach ($totalElements1 as $totalElement) {
                if (strpos($totalElement, "Gesamt") !== false) {
                    $total1 = str_replace("Gesamt:", "", $totalElement->plaintext);
                }
            }

            // Set items total
            if ($total !== '') {
                $data['items'][0]['price'] = $total;
            } else {
                $data['items'][0]['price'] = $total1;
            }

            // total price




            //Get product name
            $productName = $html->find("tr td h1", 1)->plaintext;
            $data['items'][0]['name'] = $productName;
        } else {
            $data = null;
        }

        return $data;
    }




    public function orderDetails($orderNo, $orderedDate, $arrivingDate, $itemsTotal, $shippingRate, $orderTotal)
    {
        return [
            'orderNo' => '',
            'orderedDate' => '',
            'arrivingDate' => '',
            'itemsTotal' => '',
            'shippingRate' => '',
            'orderTotal' => '',
        ];
    }

    public static function b64_str($data)
    {
        $base64 = strtr($data, '-_', '+/');
        $pretty = base64_decode($base64);
        return $pretty;
    }
}
