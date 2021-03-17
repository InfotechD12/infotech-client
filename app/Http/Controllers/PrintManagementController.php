<?php

namespace App\Http\Controllers;

use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;
use Illuminate\Support\Facades\Http;


use Illuminate\Http\Request;

class PrintManagementController extends Controller
{
    public function print_client(Request $request)
    {
        $response = Http::post('https://infotechbdg.com/api/local/print', [
            'idfaktur' => $request->id,
            'secretkey' => $request->key
        ]);

        $user = $response['data']['user'];
        $setting = $response['setting'];
        $transaksi = $response['data']['transaksi'];
        $head = $response['data'];

        $subtotal = 0;
        $diskon = 0;
        $nama = $this->substrprint($user['name'], 6, 'last');

        // return $user['printer'];

        try {

            $connector = new WindowsPrintConnector("smb://" . $user['printer']);

            $printer = new Printer($connector);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setFont(Printer::FONT_A);
            $printer->text($setting['nama'] . "\n");
            $printer->selectPrintMode();
            $printer->setFont(Printer::FONT_B);
            $printer->text($setting['alamat']  . "\n");
            $printer->text($setting['notelp'] . "," . $setting['nohp']  . "\n");
            $printer->feed();

            /* Title of receipt */
            $printer->text("----------------------------------------");
            $printer->setEmphasis(true);
            $printer->setJustification(Printer::JUSTIFY_LEFT);

            $nofaktur = $this->substrprint($head['nofaktur'], 13, 'last');
            // $printer->text($nofaktur . " " . $nama . " " . $head['updated_at']->format('d/m/Y h:i:s'));
            $printer->text($nofaktur . " " . $nama . " " . date('d/m/Y h:i:s', strtotime($head['updated_at'])));
            $printer->setEmphasis(false);
            $printer->text("----------------------------------------");
            $printer->feed(1);
            $printer->setJustification(Printer::JUSTIFY_LEFT);

            foreach ($transaksi as $value) {
                $subtotal = $subtotal + $value['nilai'];

                $qty = $this->substrprint($value['qty'], 3, 'last');
                $satuan = $this->substrprint($value['item']['satuan'], 3, 'last');
                $harga = $this->substrprint(str_replace(",", ".", number_format($value['harga'])), 9, 'last');
                $diskonval = ($value['diskon'] == 0) ? ' ' : $value['diskon'];
                $diskon = $this->substrprint($diskonval, 5, 'last');
                $nilai = $this->substrprint(str_replace(",", ".", number_format($value['nilai'])), 10, 'first');

                $nama = ($value['keterangan'] <> '') ? $value['keterangan'] : $value['item']['nama'];

                $printer->text($nama);
                $printer->feed(1);
                $printer->text($qty . " " . $satuan . "  " . $harga . "   " . $diskon . "    " . $nilai);
            }
            $printer->text("                           -------------");
            //////////////////////////////////SUBTOTAL
            $subtotal_label = $this->substrprint('Sub Total', 27, 'last');
            $subtotal_rp = $this->substrprint('Rp ' . str_replace(",", ".", number_format($subtotal)), 12, 'first');
            $printer->text($subtotal_label . ':' . $subtotal_rp);

            //////////////////////////////////DISKON
            if ($head['diskon']> 0) {
                $diskon = ($head['diskon']/ 100) * $subtotal;
                $diskon_label = $this->substrprint('Diskon (' . $head['diskon']. ' %)', 27, 'last');
                $diskon_rp = $this->substrprint($diskon, 12, 'first');
                $printer->text($diskon_label . ':' . $diskon_rp);
            } else {
                $diskon = $head['diskonrp'];
                $diskon_label = $this->substrprint('Diskon', 27, 'last');
                $diskon_rp = $this->substrprint($head['diskonrp'], 12, 'first');
                $printer->text($diskon_label . ':' . $diskon_rp);
            }

            //////////////////////////////////TOTAL
            $subtotal_label = $this->substrprint('Total', 27, 'last');
            $subtotal_rp = $this->substrprint('Rp ' . str_replace(",", ".", number_format($subtotal - $diskon)), 12, 'first');
            $printer->text($subtotal_label . ':' . $subtotal_rp);


            if ($head['bayar'] <> '') {
                ////////////////////////////////Bayar
                $bayar = $head['bayar'];
                $subtotal_label = $this->substrprint('Bayar', 27, 'last');
                $subtotal_rp = $this->substrprint('Rp ' . str_replace(",", ".", number_format($bayar)), 12, 'first');
                $printer->text($subtotal_label . ':' . $subtotal_rp);

                $printer->text("                           -------------");

                //////////////////////////////////Kembali
                $kembali = $bayar - ($subtotal - $diskon);

                $subtotal_label = $this->substrprint('Kembali', 27, 'last');
                $subtotal_rp = $this->substrprint('Rp ' . str_replace(",", ".", number_format($kembali)), 12, 'first');
                $printer->text($subtotal_label . ':' . $subtotal_rp);
            }

            $printer->feed(2);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text($setting['footer']);
            $printer->text("\n");

            if ($request->reprint == "reprint") {
                $printer->feed(2);
                $printer->text("--reprint--\n");
            }

            $printer->cut();
            $printer->close();
        } catch (Exception $e) {
            echo "TIDAK TERKONEKSI DENGAN PRINTER: " . $e->getMessage() . "\n";
        }

        return view('close');
    }

    public function substrprint($value, $number, $postition)
    {
        $str = $value;
        $strlength = strlen($str);
        if ($postition == 'first') {
            $str = ($strlength > $number) ? substr($str, 0, $number) : str_repeat(' ', ($number - $strlength)) . $str;
        } else {
            $str = ($strlength > $number) ? substr($str, 0, $number) : $str . str_repeat(' ', ($number - $strlength));
        }


        return $str;
    }
}
