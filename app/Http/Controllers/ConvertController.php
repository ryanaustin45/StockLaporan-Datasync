<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Item;
use App\Models\Pembelian;
use App\Models\Convertbom;
use App\Models\Convertpembelian;
use App\Models\Penjualan;
use App\Models\Penerimaan;
use App\Models\Laporan;
use App\Exports\PembeliansExport;
use App\Exports\PenerimaansExport;
use App\Imports\PembeliansImport;
use App\Imports\PenerimaansImport;
use App\Imports\PenjualansImport;
use App\Models\Convertpenerimaan;
use App\Models\Dprrckbom;
use App\Models\Dprbom;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;


ini_set('max_execution_time', 160000);

class ConvertController extends Controller
{

    public function index()
    {
        return view('login');
    }

    public function registration()
    {
        if (Auth::check()) {
            return view('register');
        }

        return redirect("/")->withSuccess('Opps! You do not have access');
    }

    public function postLogin(Request $request)
    {
        $request->validate([
            'email' => 'required',
            'password' => 'required',
        ]);

        $credentials = $request->only('email', 'password');
        if (Auth::attempt($credentials)) {
            return redirect()->intended('dashboard')
                ->withSuccess('You have Successfully loggedin');
        }

        return redirect("/")->withSuccess('Oppes! You have entered invalid credentials');
    }


    public function postRegistration(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
        ]);

        $data = $request->all();
        $check = $this->create($data);

        return redirect("dashboard")->withSuccess('Great! You have Successfully loggedin');
    }


    public function dashboard()
    {
        if (Auth::check()) {
            return view('Convert');
        }

        return redirect("/")->withSuccess('Opps! You do not have access');
    }


    public function create(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password'])
        ]);
    }

    public function logout()
    {
        Session::flush();
        Auth::logout();

        return Redirect('/');
    }
    // output tampilan
    public function index2()
    {
        if (Auth::check()) {
            return view('Convert');
        }

        return redirect("/")->withSuccess('Opps! You do not have access');
    }


    // IMPORT 
    public function importPembelian()
    {
        if (Auth::check()) {
            Pembelian::truncate();

            /*Outlet::create([
                'KODE' => 123,
                'NAMA' => "faker->slug",
                'ALAMAT' => "faker->text",
                'ADMIN_SPB' => "faker->content"
            ]);
    
            Outlet::where('KODE', 123)->update(['NAMA' => 'Updated title']);*/
            Excel::import(new PembeliansImport, request()->file('file'));

            /* select untuk masuk kedalam database convert pembelian dari pembelian */
            // sum error
            $pembelians1 = Pembelian::select(
                'pembelians.TANGGAL',
                'pembelians.KD_CUS',
                'pembelians.NAMAPELANGGAN',
                'items.KODE_BARANG_SAGE',
                'items.KODE_DESKRIPSI_BARANG_SAGE',
                'items.STOKING_UNIT_BOM',
                Pembelian::raw('(pembelians.BANYAK * items.RUMUS_Untuk_Purchase) / items.RUMUS_untuk_BOM as QUANTITY'),
                Pembelian::raw('(pembelians.JUMLAH / ((pembelians.BANYAK *items. RUMUS_Untuk_Purchase) / items.RUMUS_untuk_BOM))as HARGA'),
                'pembelians.JUMLAH'
            )->join('items', 'pembelians.KD_BRG', '=', 'items.KODE_BARANG_PURCHASING')->get();

            /* masuk kedalam database convert pembelian dari pembelian */
            foreach ($pembelians1 as $pembelians12) {
                if ($pembelians12->QUANTITY != 0) {
                    Convertpembelian::create([
                        'TANGGAL' => $pembelians12->TANGGAL,
                        'KODE' => $pembelians12->KD_CUS,
                        'NAMA' => $pembelians12->NAMAPELANGGAN,
                        'KODE_BARANG_SAGE' => $pembelians12->KODE_BARANG_SAGE,
                        'KODE_DESKRIPSI_BARANG_SAGE' => $pembelians12->KODE_DESKRIPSI_BARANG_SAGE,
                        'STOKING_UNIT_BOM' => $pembelians12->STOKING_UNIT_BOM,
                        'QUANTITY' => $pembelians12->QUANTITY,
                        'HARGA' => $pembelians12->HARGA,
                        'JUMLAH' => $pembelians12->JUMLAH

                    ]);
                }
            }

            //bisa dihapus sisain insert buat item doang

            /* select untuk memasukan nilai harga pada bom dapur racik dari pembelian */
            $dprrckbomhargabahan = Dprrckbom::select('dprrckboms.NAMA_BAHAN', Dprrckbom::raw('avg(convertpembelians.JUMLAH)/avg(convertpembelians.QUANTITY) as Harga'))->join(
                'convertpembelians',
                'dprrckboms.NAMA_BAHAN',
                '=',
                'convertpembelians.KODE_DESKRIPSI_BARANG_SAGE'
            )->groupBy('dprrckboms.NAMA_BAHAN')->get();

            /*memasukan nilai harga pada bahan baku bom dapur racik dari pembelian */
            foreach ($dprrckbomhargabahan as $dprrckbomhargabahans) {
                Dprrckbom::where('NAMA_BAHAN', $dprrckbomhargabahans->NAMA_BAHAN)
                    ->update(['dprrckboms.Harga' => $dprrckbomhargabahans->Harga]);
            }

            /*memasukan nilai harga pada bahan jadi bom dapur racik dari penjunmlahan bahan baku dapur racik */
            $dprrckbomhargabarang = Dprrckbom::select(
                'dprrckboms.KODE_BARANG',
                Dprrckbom::raw('sum(dprrckboms.Harga) as Harga2')
            )->groupBy('dprrckboms.KODE_BARANG')->get();
            foreach ($dprrckbomhargabarang as $dprrckbomhargabarangs) {
                Dprrckbom::where('KODE_BARANG', $dprrckbomhargabarangs->KODE_BARANG)
                    ->update(['dprrckboms.HargaBarang' => $dprrckbomhargabarangs->Harga2]);
            }

            /* select untuk memasukan nilai harga pada bom dapur pusat dari pembelian */
            $dprbom = Dprbom::select(
                'dprboms.NAMA_BAHAN',
                Dprbom::raw("IF(dprboms.NAMA_BAHAN = dprrckboms.NAMA_BARANG, dprrckboms.HargaBarang, 
            avg(convertpembelians.JUMLAH) / avg(convertpembelians.QUANTITY)) as Harga")
            )
                ->leftJoin(
                    'dprrckboms',
                    'dprboms.NAMA_BAHAN',
                    '=',
                    'dprrckboms.NAMA_BARANG'
                )->leftJoin('convertpembelians', 'dprboms.NAMA_BAHAN', '=', 'convertpembelians.KODE_DESKRIPSI_BARANG_SAGE')
                ->groupBy('dprboms.NAMA_BAHAN', 'dprrckboms.NAMA_BARANG')->get();

            /*memasukan nilai harga pada bahan baku bom dapur pusat dari bahan jadi dapur racik */
            foreach ($dprbom as $dprboms) {
                Dprbom::where('NAMA_BAHAN', $dprboms->NAMA_BAHAN)->update(['dprboms.Harga' => $dprboms->Harga]);
            }
            $dprbomhargabarang = Dprbom::select(
                'dprboms.KODE_BARANG',
                Dprbom::raw('sum(dprboms.Harga) as Harga2')
            )->groupBy('dprboms.KODE_BARANG')->get();

            /*memasukan nilai harga pada bahan jadi bom dapur pusat dari penjunmlahan bahan baku dapur pusat */
            foreach ($dprbomhargabarang as $dprbomhargabarangs) {
                Dprbom::where('KODE_BARANG', $dprbomhargabarangs->KODE_BARANG)->update(['dprboms.HargaBarang' => $dprbomhargabarangs->Harga2]);
            }

            /* select untuk memasukan nilai harga pada item dari pembelian */
            $items1 = Item::select(
                'items.KODE_BARANG_SAGE',
                Item::raw('avg(convertpembelians.JUMLAH)/avg(convertpembelians.QUANTITY) as Harga')
            )->join(
                'convertpembelians',
                'items.KODE_BARANG_SAGE',
                '=',
                'convertpembelians.KODE_BARANG_SAGE'
            )->groupBy('items.KODE_BARANG_SAGE')->get();

            /*memasukan nilai harga pada item dari pembelian */
            foreach ($items1 as $itemss1) {
                Item::where('KODE_BARANG_SAGE', $itemss1->KODE_BARANG_SAGE)->update(['items.Harga' => $itemss1->Harga]);
            }

            /* select untuk memasukan nilai harga pada item dari dapur racik */
            $items1123 = Item::select('dprrckboms.NAMA_BARANG', 'dprrckboms.HargaBarang')->join(
                'dprrckboms',
                'items.KODE_DESKRIPSI_BARANG_SAGE',
                '=',
                'dprrckboms.NAMA_BARANG'
            )->get();

            /*memasukan nilai harga pada item dari dapur racik */
            foreach ($items1123 as $itemss1123) {
                Item::where('KODE_DESKRIPSI_BARANG_SAGE', $itemss1123->NAMA_BARANG)->update(['items.Harga' => $itemss1123->HargaBarang]);
            }

            /* select untuk memasukan nilai harga pada item dari dapur pusat */
            $items11234 = Item::select('dprboms.NAMA_BARANG', 'dprboms.HargaBarang')->join(
                'dprboms',
                'items.KODE_DESKRIPSI_BARANG_SAGE',
                '=',
                'dprboms.NAMA_BARANG'
            )->get();

            /*memasukan nilai harga pada item dari dapur pusat */
            foreach ($items11234 as $itemss11234) {
                Item::where('KODE_DESKRIPSI_BARANG_SAGE', $itemss11234->NAMA_BARANG)->update(['items.Harga' => $itemss11234->HargaBarang]);
            }
            /*nentuin harga if ada di dapur racik, pake selek yang bawah
            SELECT dprboms.NAMA_BAHAN, IF(dprboms.NAMA_BAHAN=dprrckboms.NAMA_BARANG, dprrckboms.HargaBarang, avg(convertpembelians.JUMLAH)/avg(convertpembelians.QUANTITY)) as tes FROM dprboms LEFT JOIN dprrckboms ON dprboms.NAMA_BAHAN = dprrckboms.NAMA_BARANG LEFT JOIN convertpembelians ON dprboms.NAMA_BAHAN = convertpembelians.KODE_DESKRIPSI_BARANG_SAGE GROUP BY dprboms.NAMA_BAHAN;
    
            SELECT IF(dprboms.NAMA_BAHAN=dprrckboms.NAMA_BARANG, "MORE", "LESS")
            FROM dprboms; 
            more = SELECT dprrckboms.NAMA_BAHAN,dprrckboms.Harga,dprrckboms.NAMA_BARANG,sum(dprrckboms.Harga) FROM `dprrckboms` GROUP BY dprrckboms.NAMA_BARANG as harga;
            less = else langsung dari convert pembelian avg(convertpembelians.JUMLAH)/avg(convertpembelians.QUANTITY) as Harga
            
            Dprbom::raw("'IF'('dprboms.NAMA_BAHAN' '=' 'dprrckboms.NAMA_BARANG', 'dprrckboms.HargaBarang', ''avg'('convertpembelians.JUMLAH')' '/' ''avg'('convertpembelians.QUANTITY')') as Harga")
            */

            $PembelianSaldo = Convertpembelian::select(
                'TANGGAL',
                'KODE',
                'NAMA',
                'KODE_BARANG_SAGE',
                'KODE_DESKRIPSI_BARANG_SAGE',
                'STOKING_UNIT_BOM',
                Convertpembelian::raw('sum(QUANTITY) as QUANTITY2'),
                Convertpembelian::raw('sum(JUMLAH) as JUMLAH2'),
                Convertpembelian::raw('sum(JUMLAH) / sum(QUANTITY) as HARGA2'),
            )->groupBy(
                'TANGGAL',
                'KODE',
                'KODE_BARANG_SAGE',
            )->get();

            foreach ($PembelianSaldo as $PembelianSaldos) {
                $temp = Laporan::where('TANGGAL', $PembelianSaldos->TANGGAL)->where('KODE', $PembelianSaldos->KODE)->where('KODE_BARANG_SAGE', $PembelianSaldos->KODE_BARANG_SAGE)
                    ->update([
                        'Pembelian_Unit' => $PembelianSaldos->QUANTITY2, 'Pembelian_Quantity' => $PembelianSaldos->HARGA2,
                        'Pembelian_Price' => $PembelianSaldos->JUMLAH2
                    ]);
                if ($temp) {
                    continue;
                } else {
                    Laporan::create([
                        'TANGGAL' => $PembelianSaldos->TANGGAL,
                        'KODE' => $PembelianSaldos->KODE,
                        'NAMA' => $PembelianSaldos->NAMA,
                        'KODE_BARANG_SAGE' => $PembelianSaldos->KODE_BARANG_SAGE,
                        'KODE_DESKRIPSI_BARANG_SAGE' => $PembelianSaldos->KODE_DESKRIPSI_BARANG_SAGE,
                        'STOKING_UNIT_BOM' => $PembelianSaldos->STOKING_UNIT_BOM,
                        'Pembelian_Unit' => $PembelianSaldos->QUANTITY2,
                        'Pembelian_Quantity' => $PembelianSaldos->HARGA2,
                        'Pembelian_Price' => $PembelianSaldos->JUMLAH2,
                    ]);
                }
            }
            return back()->with('success', 'Berhasil Upload');
            //response()->json(['success' => 'file berhasil di upload']);
        }

        return redirect("/")->withSuccess('Opps! You do not have access');
    }

    public function importPenerimaan()
    {
        if (Auth::check()) {
            Penerimaan::truncate();

            Excel::import(new PenerimaansImport, request()->file('file'));

            $penerimaanss = Penerimaan::join('items', 'items.KODE_BARANG_PURCHASING', '=', 'penerimaans.KD_BHN')
                ->join('outlets', 'outlets.KODE', '=', 'penerimaans.PENERIMA')
                ->select(
                    'penerimaans.TANGGAL as TANGGAL',
                    'penerimaans.PENERIMA as PENERIMA',
                    'outlets.NAMA as ALAMAT',
                    'penerimaans.DARI as DARI',
                    'penerimaans.NAMAPELANGGAN as NAMAPELANGGAN',
                    'items.KODE_BARANG_SAGE as KODE_BARANG_SAGE',
                    'items.KODE_DESKRIPSI_BARANG_SAGE as KODE_DESKRIPSI_BARANG_SAGE',
                    'items.STOKING_UNIT_BOM as STOKING_UNIT_BOM',
                    Penerimaan::raw('(sum(penerimaans.QT_TERIMA) * items.RUMUS_Untuk_Purchase) / items.RUMUS_untuk_BOM as QUANTITY'),
                    Penerimaan::raw('avg(items.Harga) as Harga'),
                )->groupBy('penerimaans.KD_BHN', 'penerimaans.PENERIMA', 'penerimaans.DARI', 'penerimaans.TANGGAL')->get();


            // menjadi convert penerimaan 
            foreach ($penerimaanss as $penerimaansss) {
                if ($penerimaansss->QUANTITY != 0) {
                    Convertpenerimaan::create([
                        'TANGGAL' => $penerimaansss->TANGGAL,
                        'PENERIMA' => $penerimaansss->PENERIMA,
                        'NAMAPENERIMA' => $penerimaansss->ALAMAT,
                        'DARI' => $penerimaansss->DARI,
                        'NAMADARI' => $penerimaansss->NAMAPELANGGAN,
                        'KODE_BARANG_SAGE' => $penerimaansss->KODE_BARANG_SAGE,
                        'KODE_DESKRIPSI_BARANG_SAGE' => $penerimaansss->KODE_DESKRIPSI_BARANG_SAGE,
                        'STOKING_UNIT_BOM' => $penerimaansss->STOKING_UNIT_BOM,
                        'QUANTITY' => $penerimaansss->QUANTITY,
                        'HARGA' => $penerimaansss->Harga,
                        'JUMLAH' => $penerimaansss->QUANTITY * $penerimaansss->Harga
                    ]);
                }
            }
            // dari dimasukan ke pengiriman
            $PenerimanSaldo = Convertpenerimaan::select(
                'convertpenerimaans.TANGGAL',
                'convertpenerimaans.DARI',
                'convertpenerimaans.NAMADARI',
                'convertpenerimaans.KODE_BARANG_SAGE',
                'convertpenerimaans.KODE_DESKRIPSI_BARANG_SAGE',
                'convertpenerimaans.STOKING_UNIT_BOM',
                Convertpenerimaan::raw('sum(convertpenerimaans.QUANTITY) as COST'),
                'convertpenerimaans.HARGA',
                'convertpenerimaans.JUMLAH',
            )->groupBy('convertpenerimaans.TANGGAL', 'convertpenerimaans.DARI', 'convertpenerimaans.KODE_BARANG_SAGE')->get();

            foreach ($PenerimanSaldo as $PenerimanSaldos) {
                $temp = Laporan::Where('TANGGAL', $PenerimanSaldos->TANGGAL)->where('KODE', $PenerimanSaldos->DARI)
                    ->where('KODE_BARANG_SAGE', $PenerimanSaldos->KODE_BARANG_SAGE)
                    ->update([
                        'Pengiriman_Unit' => $PenerimanSaldos->COST
                    ]);
                if ($temp) {
                    continue;
                } else {
                    Laporan::create([
                        'TANGGAL' => $PenerimanSaldos->TANGGAL,
                        'KODE' => $PenerimanSaldos->DARI,
                        'NAMA' => $PenerimanSaldos->NAMADARI,
                        'KODE_BARANG_SAGE' => $PenerimanSaldos->KODE_BARANG_SAGE,
                        'KODE_DESKRIPSI_BARANG_SAGE' => $PenerimanSaldos->KODE_DESKRIPSI_BARANG_SAGE,
                        'STOKING_UNIT_BOM' => $PenerimanSaldos->STOKING_UNIT_BOM,
                        'Pengiriman_Unit' => $PenerimanSaldos->COST
                    ]);
                }
            }



            // menjadi convert bomdpr 
            $Boms1 = Convertpenerimaan::join('dprboms', function ($join) {
                $join->on(
                    'dprboms.NAMA_BARANG',
                    '=',
                    'convertpenerimaans.KODE_DESKRIPSI_BARANG_SAGE'
                )->on(
                    Convertpenerimaan::raw('LEFT(dprboms.KODE_BARANG,4)'),
                    '=',
                    'convertpenerimaans.DARI'
                );
            })->select(
                'convertpenerimaans.TANGGAL',
                'convertpenerimaans.DARI',
                'convertpenerimaans.NAMADARI',
                'convertpenerimaans.KODE_BARANG_SAGE',
                Convertpenerimaan::raw('sum(convertpenerimaans.QUANTITY) as unit2'),
                'convertpenerimaans.KODE_DESKRIPSI_BARANG_SAGE',
                'convertpenerimaans.STOKING_UNIT_BOM',
                Convertpenerimaan::raw('RIGHT(dprboms.KODE_BAHAN, 11) AS kodeBarang'),
                'dprboms.NAMA_BAHAN',
                'dprboms.SATUAN_BAHAN',
                Convertpenerimaan::raw('sum(convertpenerimaans.QUANTITY) * dprboms.BANYAK AS unit'),
                'dprboms.Harga AS hargasatuan',
                'dprboms.HargaBarang AS hargabom',
            )->groupBy('convertpenerimaans.TANGGAL', 'convertpenerimaans.DARI', 'dprboms.NAMA_BAHAN', 'convertpenerimaans.KODE_BARANG_SAGE')->get();

            foreach ($Boms1 as $Boms11) {
                Convertbom::create([
                    'TANGGAL' => $Boms11->TANGGAL,
                    'KODE' => $Boms11->DARI,
                    'NAMA' => $Boms11->NAMADARI,
                    'KODE_BARANG_SAGE' => $Boms11->kodeBarang,
                    'KODE_DESKRIPSI_BARANG_SAGE' => $Boms11->NAMA_BAHAN,
                    'STOKING_UNIT_BOM' => $Boms11->SATUAN_BAHAN,
                    'QUANTITY' => $Boms11->unit,
                    'HARGA' => $Boms11->hargasatuan,
                    'JUMLAH' => $Boms11->unit * $Boms11->hargasatuan

                ]);
                Laporan::Where('TANGGAL', $Boms11->TANGGAL)->where('KODE', $Boms11->DARI)->where('KODE_BARANG_SAGE', $Boms11->KODE_BARANG_SAGE)
                    ->update([
                        'Penerimaan_Unit' => $Boms11->unit2,
                        'Penerimaan_Quantity' => $Boms11->hargabom,
                        'Penerimaan_Price' => $Boms11->unit2 * $Boms11->hargabom
                    ]);/*
            if ($temp) {
                continue;
            } else {
                Laporan::Where('TANGGAL', '<>', $Boms11->TANGGAL)->where('KODE', '<>', $Boms11->DARI)->where('KODE_BARANG_SAGE', '<>', $Boms11->KODE_BARANG_SAGE)->create([
                    'TANGGAL' => $Boms11->TANGGAL,
                    'KODE' => $Boms11->DARI,
                    'NAMA' => $Boms11->NAMADARI,
                    'KODE_BARANG_SAGE' => $Boms11->KODE_BARANG_SAGE,
                    'KODE_DESKRIPSI_BARANG_SAGE' => $Boms11->KODE_DESKRIPSI_BARANG_SAGE,
                    'STOKING_UNIT_BOM' => $Boms11->STOKING_UNIT_BOM,
                    'Penerimaan_Unit' => $Boms11->unit2,
                    'Penerimaan_Quantity' => $Boms11->hargabom,
                    'Penerimaan_Price' => Laporan::raw('Penerimaan_Unit*Penerimaan_Quantity')
                ]);
            }*/
            }
            // menjadi convert bomdprrck
            $Boms2 = Convertpenerimaan::join('dprrckboms', function ($join) {
                $join->on(
                    'dprrckboms.NAMA_BARANG',
                    '=',
                    'convertpenerimaans.KODE_DESKRIPSI_BARANG_SAGE'
                )->on(
                    Convertpenerimaan::raw('LEFT(dprrckboms.KODE_BARANG,4)'),
                    '=',
                    'convertpenerimaans.DARI'
                );
            })->select(
                'convertpenerimaans.TANGGAL',
                'convertpenerimaans.DARI',
                'convertpenerimaans.NAMADARI',
                'convertpenerimaans.KODE_BARANG_SAGE',
                Convertpenerimaan::raw('sum(convertpenerimaans.QUANTITY)as unit2'),
                'convertpenerimaans.KODE_DESKRIPSI_BARANG_SAGE',
                'convertpenerimaans.STOKING_UNIT_BOM',
                Convertpenerimaan::raw('RIGHT(dprrckboms.KODE_BAHAN, 11) AS kodeBarang'),
                'dprrckboms.NAMA_BAHAN',
                'dprrckboms.SATUAN_BAHAN',
                Convertpenerimaan::raw('sum(convertpenerimaans.QUANTITY)* dprrckboms.BANYAK AS unit'),
                'dprrckboms.Harga AS hargasatuan',
                'dprrckboms.HargaBarang AS hargabom',
            )->groupBy('convertpenerimaans.TANGGAL', 'convertpenerimaans.DARI', 'dprrckboms.NAMA_BAHAN', 'convertpenerimaans.KODE_BARANG_SAGE')->get();

            foreach ($Boms2 as $Boms22) {
                Convertbom::create([
                    'TANGGAL' => $Boms22->TANGGAL,
                    'KODE' => $Boms22->DARI,
                    'NAMA' => $Boms22->NAMADARI,
                    'KODE_BARANG_SAGE' => $Boms22->kodeBarang,
                    'KODE_DESKRIPSI_BARANG_SAGE' => $Boms22->NAMA_BAHAN,
                    'STOKING_UNIT_BOM' => $Boms22->SATUAN_BAHAN,
                    'QUANTITY' => $Boms22->unit,
                    'HARGA' => $Boms22->hargasatuan,
                    'JUMLAH' => $Boms22->unit * $Boms22->hargasatuan
                ]);
                Laporan::Where('TANGGAL', $Boms22->TANGGAL)->where('KODE', $Boms22->DARI)->where('KODE_BARANG_SAGE', $Boms22->KODE_BARANG_SAGE)
                    ->update([
                        'Penerimaan_Unit' => $Boms22->unit2,
                        'Penerimaan_Quantity' => $Boms22->hargabom,
                        'Penerimaan_Price' =>  $Boms22->unit2 * $Boms22->hargabom
                    ]);/*
            $temp = Laporan::Where('TANGGAL', $Boms22->TANGGAL)->where('KODE', $Boms22->DARI)->where('KODE_BARANG_SAGE', $Boms22->KODE_BARANG_SAGE)
                ->update([
                    'Penerimaan_Unit' => $Boms22->unit2,
                    'Penerimaan_Quantity' => $Boms22->hargabom,
                    'Penerimaan_Price' => $Boms22->unit2 * $Boms22->hargabom
                ]);
            if ($temp) {
                continue;
            } else {
                Laporan::Where('TANGGAL', '<>', $Boms22->TANGGAL)->where('KODE', '<>', $Boms22->DARI)->where('KODE_BARANG_SAGE', '<>', $Boms22->KODE_BARANG_SAGE)->create([
                    'TANGGAL' => $Boms22->TANGGAL,
                    'KODE' => $Boms22->DARI,
                    'NAMA' => $Boms22->NAMADARI,
                    'KODE_BARANG_SAGE' => $Boms22->KODE_BARANG_SAGE,
                    'KODE_DESKRIPSI_BARANG_SAGE' => $Boms22->KODE_DESKRIPSI_BARANG_SAGE,
                    'STOKING_UNIT_BOM' => $Boms22->STOKING_UNIT_BOM,
                    'Penerimaan_Unit' => $Boms22->unit2,
                    'Penerimaan_Quantity' => $Boms22->hargabom,
                    'Penerimaan_Price' => $Boms22->unit2 * $Boms22->hargaboms
                ]);
            }*/
            }


            // penerimaan penerima dimasukan ke Laporan
            $PenerimanSaldo2 = Convertpenerimaan::select(
                'convertpenerimaans.TANGGAL',
                'convertpenerimaans.PENERIMA',
                'convertpenerimaans.NAMAPENERIMA',
                'convertpenerimaans.KODE_BARANG_SAGE',
                'convertpenerimaans.KODE_DESKRIPSI_BARANG_SAGE',
                'convertpenerimaans.STOKING_UNIT_BOM',
                Convertpenerimaan::raw('sum(convertpenerimaans.QUANTITY) as COST'),
                'convertpenerimaans.HARGA',
                'convertpenerimaans.JUMLAH',
            )->groupBy('convertpenerimaans.TANGGAL', 'convertpenerimaans.PENERIMA', 'convertpenerimaans.KODE_BARANG_SAGE')->get();

            foreach ($PenerimanSaldo2 as $PenerimanSaldo2s) {
                $temp = Laporan::where('TANGGAL', $PenerimanSaldo2s->TANGGAL)->where('KODE', $PenerimanSaldo2s->PENERIMA)->where('KODE_BARANG_SAGE', $PenerimanSaldo2s->KODE_BARANG_SAGE)
                    ->update([
                        'Bom_Unit' => $PenerimanSaldo2s->COST,

                        'Penerimaan_Unit' => Laporan::raw('IFNULL(Bom_Unit, 0) + IFNULL(Penerimaan_Unit, 0)'),
                        'Penerimaan_Quantity' => $PenerimanSaldo2s->HARGA,
                        'Penerimaan_Price' => Laporan::raw('IFNULL(Penerimaan_Unit, 0) * IFNULL(Penerimaan_Quantity, 0)'),
                    ]);
                if ($temp) {
                    continue;
                } else {
                    Laporan::create([
                        'TANGGAL' => $PenerimanSaldo2s->TANGGAL,
                        'KODE' => $PenerimanSaldo2s->PENERIMA,
                        'NAMA' => $PenerimanSaldo2s->NAMAPENERIMA,
                        'KODE_BARANG_SAGE' => $PenerimanSaldo2s->KODE_BARANG_SAGE,
                        'KODE_DESKRIPSI_BARANG_SAGE' => $PenerimanSaldo2s->KODE_DESKRIPSI_BARANG_SAGE,
                        'STOKING_UNIT_BOM' => $PenerimanSaldo2s->STOKING_UNIT_BOM,
                        'Penerimaan_Unit' => $PenerimanSaldo2s->COST,
                        'Penerimaan_Quantity' => $PenerimanSaldo2s->HARGA,
                        'Penerimaan_Price' => $PenerimanSaldo2s->COST * $PenerimanSaldo2s->HARGA
                    ]);
                }
            }



            $bomconvertsaldo = Convertbom::select(
                'convertboms.TANGGAL',
                'convertboms.KODE',
                'convertboms.NAMA',
                'convertboms.KODE_BARANG_SAGE',
                'convertboms.KODE_DESKRIPSI_BARANG_SAGE',
                'convertboms.STOKING_UNIT_BOM',
                Convertbom::raw('sum(convertboms.QUANTITY) as unit'),
            )->groupBy('convertboms.TANGGAL', 'convertboms.KODE', 'convertboms.KODE_BARANG_SAGE')->get();

            foreach ($bomconvertsaldo as $bomconvertsaldos) {
                $temp = Laporan::where('TANGGAL', $bomconvertsaldos->TANGGAL)->where('KODE', $bomconvertsaldos->KODE)->where('KODE_BARANG_SAGE', $bomconvertsaldos->KODE_BARANG_SAGE)
                    ->update([
                        'Bom_Unit' => $bomconvertsaldos->unit
                    ]);
                if ($temp) {
                    continue;
                } else {
                    Laporan::create([
                        'TANGGAL' => $bomconvertsaldos->TANGGAL,
                        'KODE' => $bomconvertsaldos->KODE,
                        'NAMA' => $bomconvertsaldos->NAMA,
                        'KODE_BARANG_SAGE' => $bomconvertsaldos->KODE_BARANG_SAGE,
                        'KODE_DESKRIPSI_BARANG_SAGE' => $bomconvertsaldos->KODE_DESKRIPSI_BARANG_SAGE,
                        'STOKING_UNIT_BOM' => $bomconvertsaldos->STOKING_UNIT_BOM,
                        'Bom_Unit' => $bomconvertsaldos->unit,
                    ]);
                }
            }
            return back()->with('success', 'Berhasil Upload');
        }

        return redirect("/")->withSuccess('Opps! You do not have access');
    }

    public function importPenjualan()
    {

        if (Auth::check()) {
            Penjualan::truncate();

            Excel::import(new PenjualansImport, request()->file('file'));
            Convertbom::truncate();


            $Boms1 = Penjualan::join('boms', function ($join) {
                $join->on(
                    'boms.NAMA_BARANG',
                    '=',
                    'penjualans.Barang'
                )->on(
                    Penjualan::raw('LEFT(boms.KODE_BARANG,4)'),
                    '=',
                    'penjualans.KODE_OUTLET'
                );
            })->select(
                'penjualans.TANGGAL',
                'penjualans.KODE_OUTLET',
                'penjualans.Outlet',
                Penjualan::raw('RIGHT(boms.KODE_BAHAN, 11) AS kodeBarang'),
                'boms.NAMA_BAHAN',
                'boms.SATUAN_BAHAN',
                Penjualan::raw('penjualans.Banyak * boms.BANYAK AS QUANTITY'),
                Penjualan::raw('penjualans.Jumlah / penjualans.Banyak AS HARGA'),
            )->get();

            foreach ($Boms1 as $Boms11) {
                if ($Boms11->QUANTITY != 0) {
                    Convertbom::create([
                        'TANGGAL' => $Boms11->TANGGAL,
                        'KODE' => $Boms11->KODE_OUTLET,
                        'NAMA' => $Boms11->Outlet,
                        'KODE_BARANG_SAGE' => $Boms11->kodeBarang,
                        'KODE_DESKRIPSI_BARANG_SAGE' => $Boms11->NAMA_BAHAN,
                        'STOKING_UNIT_BOM' => $Boms11->SATUAN_BAHAN,
                        'QUANTITY' => $Boms11->QUANTITY,
                        'HARGA' => $Boms11->HARGA,
                        'JUMLAH' => $Boms11->QUANTITY * $Boms11->HARGA
                    ]);
                }
            }
            $bomconvertsaldo = Convertbom::select(
                'convertboms.TANGGAL',
                'convertboms.KODE',
                'convertboms.NAMA',
                'convertboms.KODE_BARANG_SAGE',
                'convertboms.KODE_DESKRIPSI_BARANG_SAGE',
                'convertboms.STOKING_UNIT_BOM',
                Convertpenerimaan::raw('sum(convertboms.QUANTITY) as unit'),
            )->groupBy('convertboms.TANGGAL', 'convertboms.KODE', 'convertboms.KODE_BARANG_SAGE')->get();

            foreach ($bomconvertsaldo as $bomconvertsaldos) {
                $temp = Laporan::where('TANGGAL', $bomconvertsaldos->TANGGAL)->where('KODE', $bomconvertsaldos->KODE)->where('KODE_BARANG_SAGE', $bomconvertsaldos->KODE_BARANG_SAGE)
                    ->update([
                        'Bom_Quantity' => $bomconvertsaldos->unit,
                        'Bom_Unit' => Laporan::raw('IFNULL(Bom_Unit, 0) + IFNULL(Bom_Quantity, 0)'),
                    ]);
                if ($temp) {
                    continue;
                } else {
                    Laporan::create([
                        'TANGGAL' => $bomconvertsaldos->TANGGAL,
                        'KODE' => $bomconvertsaldos->KODE,
                        'NAMA' => $bomconvertsaldos->NAMA,
                        'KODE_BARANG_SAGE' => $bomconvertsaldos->KODE_BARANG_SAGE,
                        'KODE_DESKRIPSI_BARANG_SAGE' => $bomconvertsaldos->KODE_DESKRIPSI_BARANG_SAGE,
                        'STOKING_UNIT_BOM' => $bomconvertsaldos->STOKING_UNIT_BOM,
                        'Bom_Unit' => $bomconvertsaldos->unit,
                    ]);
                }
            }
            /* boms 22
        $Boms2 = Convertbom::join('dprboms', function ($join) {
            $join->on(
                'dprboms.NAMA_BARANG',
                '=',
                'convertboms.KODE_DESKRIPSI_BARANG_SAGE'
            )->on(
                Penjualan::raw("LEFT(dprboms.KODE_BARANG,4)"),
                '=',
                'convertboms.KODE'
            );
        })->select(
            'convertboms.TANGGAL',
            'convertboms.KODE',
            'convertboms.NAMA',
            Penjualan::raw('RIGHT(dprboms.KODE_BAHAN, 11) AS kodeBarang'),
            'dprboms.NAMA_BAHAN',
            'dprboms.SATUAN_BAHAN',
            Penjualan::raw('convertboms.QUANTITY * dprboms.BANYAK AS QUANTITY'),
        )->get();

        foreach ($Boms2 as $Boms22) {
            Convertdprbom::create([
                'TANGGAL' => $Boms22->TANGGAL,
                'KODE' => $Boms22->KODE_OUTLET,
                'NAMA' => $Boms22->Outlet,
                'KODE_BARANG_SAGE' => $Boms22->kodeBarang,
                'KODE_DESKRIPSI_BARANG_SAGE' => $Boms22->NAMA_BAHAN,
                'STOKING_UNIT_BOM' => $Boms22->SATUAN_BAHAN,
                'QUANTITY' => $Boms22->QUANTITY,
            ]);
        }

        //bom33
        $Boms3 = Convertdprbom::join('dprrckboms', function ($join) {
            $join->on(
                'dprrckboms.NAMA_BARANG',
                '=',
                'convertdprboms.KODE_DESKRIPSI_BARANG_SAGE'
            )->on(
                Penjualan::raw("LEFT(dprrckboms.KODE_BARANG,4)"),
                '=',
                'convertdprboms.KODE'
            );
        })->select(
            'convertdprboms.TANGGAL',
            'convertdprboms.KODE',
            'convertdprboms.NAMA',
            Penjualan::raw('RIGHT(dprrckboms.KODE_BAHAN, 11) AS kodeBarang'),
            'dprrckboms.NAMA_BAHAN',
            'dprrckboms.SATUAN_BAHAN',
            Penjualan::raw('convertdprboms.QUANTITY * dprrckboms.BANYAK AS QUANTITY'),
        )->get();

        foreach ($Boms3 as $Boms33) {
            Convertdprrckbom::create([
                'TANGGAL' => $Boms33->TANGGAL,
                'KODE' => $Boms33->KODE_OUTLET,
                'NAMA' => $Boms33->Outlet,
                'KODE_BARANG_SAGE' => $Boms33->kodeBarang,
                'KODE_DESKRIPSI_BARANG_SAGE' => $Boms33->NAMA_BAHAN,
                'STOKING_UNIT_BOM' => $Boms33->SATUAN_BAHAN,
                'QUANTITY' => $Boms33->QUANTITY,
            ]);
        }
            */
            return back()->with('success', 'Berhasil Upload');
        }

        return redirect("/")->withSuccess('Opps! You do not have access');
    }

    // EXPORT
    public function exportPembelian()
    {
        if (Auth::check()) {
            return Excel::download(new PembeliansExport, 'PembeliansConvert.xlsx');
        }

        return redirect("/")->withSuccess('Opps! You do not have access');
    }

    public function exportPenerimaan()
    {
        if (Auth::check()) {
            return Excel::download(new PenerimaansExport, 'PenerimaansInternalConvert.xlsx');
        }

        return redirect("/")->withSuccess('Opps! You do not have access');
    }



    //Menampilkan Tabel Convert Pembelian
    public function pembeliantes()
    {
        if (Auth::check()) {
            $pembelians = Convertpembelian::get();
            return view('Pembelian/Semua', compact('pembelians'));
        }

        return redirect("/")->withSuccess('Opps! You do not have access');
    }

    //Menampilkan Tabel Convert pengiriman internal menjadi penerimaan
    public function penerimaantes()
    {
        if (Auth::check()) {
            $penerimaanss = Convertpenerimaan::get();
            return view('Penerimaan/Semua', compact('penerimaanss'));
        }

        return redirect("/")->withSuccess('Opps! You do not have access');
    }

    //Menampilkan Tabel Convert pengiriman internal menjadi pengiriman
    public function pengirimantes()
    {
        if (Auth::check()) {
            $penerimaanss = Convertpenerimaan::get();
            return view('Pengiriman/Semua', compact('penerimaanss'));
        }

        return redirect("/")->withSuccess('Opps! You do not have access');
    }
    public function penjualan()
    {
        if (Auth::check()) {
            $penjualanss = Convertbom::get();

            return view('Penjualan', compact('penjualanss'));
        }

        return redirect("/")->withSuccess('Opps! You do not have access');
    }

    // mencari tabel Pembelian Convert
    public function cariPembelian(Request $request)
    {

        if (Auth::check()) {
            // menangkap data pencarian
            $cari = $request->cari;

            // mengambil data dari table pegawai sesuai pencarian data
            $pembelians = Pembelian::join('items', 'KODE_BARANG_PURCHASING', '=', 'KD_BRG')
                ->join('outlets', 'KODE', '=', 'KD_CUS')
                ->select(
                    'TANGGAL',
                    'KODE',
                    'NAMA',
                    'KODE_BARANG_SAGE',
                    'KODE_DESKRIPSI_BARANG_SAGE',
                    'STOKING_UNIT_BOM',
                    Pembelian::raw('sum(BANYAK * RUMUS_Untuk_Purchase / RUMUS_untuk_BOM)as QUANTITY'),
                    'HARGA',
                    'JUMLAH'
                )->groupBy('TANGGAL', 'KODE', 'NAMA', 'KODE_BARANG_SAGE', 'KODE_DESKRIPSI_BARANG_SAGE', 'STOKING_UNIT_BOM', 'HARGA', 'JUMLAH')->where('KODE', 'like', "%" . $cari . "%")->get();

            return view('Pembelian/Semua', compact('pembelians'));
        }

        return redirect("/")->withSuccess('Opps! You do not have access');
    }

    public function FilterPembelian(Request $request)
    {
        if (Auth::check()) {
            // menangkap data pencarian
            $start_date = $request->start_date;
            $end_date = $request->end_date;


            // mengambil data dari table pegawai sesuai pencarian data
            $pembelians = Pembelian::join('items', 'KODE_BARANG_PURCHASING', '=', 'KD_BRG')
                ->join('outlets', 'KODE', '=', 'KD_CUS')
                ->select(
                    'TANGGAL',
                    'KODE',
                    'NAMA',
                    'KODE_BARANG_SAGE',
                    'KODE_DESKRIPSI_BARANG_SAGE',
                    'STOKING_UNIT_BOM',
                    Pembelian::raw('sum(BANYAK * RUMUS_Untuk_Purchase / RUMUS_untuk_BOM)as QUANTITY'),
                    'HARGA',
                    'JUMLAH'
                )->groupBy('TANGGAL', 'KODE', 'NAMA', 'KODE_BARANG_SAGE', 'KODE_DESKRIPSI_BARANG_SAGE', 'STOKING_UNIT_BOM', 'HARGA', 'JUMLAH')->whereDate('TANGGAL', '>=', $start_date)->whereDate('TANGGAL', '<=', $end_date)->get();

            return view('Pembelian/Semua', compact('pembelians'));
        }

        return redirect("/")->withSuccess('Opps! You do not have access');
    }

    public function deletedata()
    {
        if (Auth::check()) {
            Convertbom::truncate();
            Convertpembelian::truncate();
            Convertpenerimaan::truncate();
            Laporan::truncate();
            Pembelian::truncate();
            Penerimaan::truncate();
            Penjualan::truncate();

            return back();
        }

        return redirect("/")->withSuccess('Opps! You do not have access');
    }
}
