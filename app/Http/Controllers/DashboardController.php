<?php

namespace App\Http\Controllers;

use App\Models\Kategori;
use App\Models\Member;
use App\Models\Pembelian;
use App\Models\Pengeluaran;
use App\Models\Penjualan;
use App\Models\Produk;
use App\Models\Supplier;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $tanggal_awal = $request->input('start_date', date('Y-m-01'));
        $tanggal_akhir = $request->input('end_date', date('Y-m-d'));

        $kategori = Kategori::count();
        $produk = Produk::count();
        $supplier = Supplier::count();
        $member = Member::count();

        $data_tanggal = [];
        $data_pendapatan = [];

        $data_tanggal = array();
        $data_pendapatan = array();

        $tanggal_loop = $tanggal_awal;
        while (strtotime($tanggal_loop) <= strtotime($tanggal_akhir)) {
            $data_tanggal[] = (int) substr($tanggal_loop, 8, 2);

            $total_penjualan = Penjualan::where('created_at', 'LIKE', "%$tanggal_loop%")->sum('bayar');
            $total_pembelian = Pembelian::where('created_at', 'LIKE', "%$tanggal_loop%")->sum('bayar');
            $total_pengeluaran = Pengeluaran::where('created_at', 'LIKE', "%$tanggal_loop%")->sum('nominal');

            $pendapatan = $total_penjualan - $total_pembelian - $total_pengeluaran;
            $data_pendapatan[] += $pendapatan;

            $tanggal_loop = date('Y-m-d', strtotime("+1 day", strtotime($tanggal_loop)));
        }
        $total_produk_terjual_today = \DB::table('penjualan_detail')
        ->join('produk', 'penjualan_detail.id_produk', '=', 'produk.id_produk')
        ->whereBetween('penjualan_detail.created_at', ["$tanggal_awal", "$tanggal_akhir"])
        ->count();
      // dd($total_produk_terjual_today->toSql(), $total_produk_terjual_today->getBindings());

        
        $total = \DB::table('penjualan_detail')
        ->join('produk', 'penjualan_detail.id_produk', '=', 'produk.id_produk')
        ->select(
        \DB::raw('SUM(penjualan_detail.subtotal) as total_revenue'), 
        \DB::raw('SUM(penjualan_detail.subtotal - (produk.harga_beli * penjualan_detail.jumlah)) as total_net_revenue') 
        ) 
        ->whereBetween('penjualan_detail.created_at', [$tanggal_awal, $tanggal_akhir])  
        ->first();

        $total_revenue = $total ? $total->total_revenue : 0;
        $total_net_revenue = $total ? $total->total_net_revenue : 0;

        $pengeluaran = \DB::table('pengeluaran')
        ->select(
            \DB::raw('SUM(nominal) as total_pengeluaran')
        )
        ->whereBetween('pengeluaran.created_at', [$tanggal_awal, $tanggal_akhir])
        ->first();

        $total_pengeluaran = $pengeluaran->total_pengeluaran;

        $keuntungan = \DB::table('penjualan_detail')
        ->join('produk', 'penjualan_detail.id_produk', '=', 'produk.id_produk')
        ->select(
            'penjualan_detail.id_produk',
            'produk.nama_produk as name',
            'produk.harga_jual',
            'produk.harga_beli',
           \DB::raw('SUM(penjualan_detail.jumlah * (penjualan_detail.harga_jual - produk.harga_beli)) as keuntungan')
        )
        ->whereBetween('penjualan_detail.created_at', [$tanggal_awal, $tanggal_akhir])  
        ->groupBy('penjualan_detail.id_produk', 'produk.nama_produk', 'produk.harga_jual', 'produk.harga_beli')
        ->get();

        //dd($keuntungan);

        $produk_nama = $keuntungan->pluck('name');
        $produk_pendapatan = $keuntungan->pluck('keuntungan');



        $tanggal_awal = date('Y-m-01');

        if (auth()->user()->level == 1) {
            return view('admin.dashboard', compact('kategori', 'produk_nama', 'total_pengeluaran', 'produk_pendapatan', 'produk', 'supplier', 'member', 'tanggal_awal', 'tanggal_akhir', 'data_tanggal', 'data_pendapatan', 'total_revenue', 'total_net_revenue', 'total_produk_terjual_today'));
        } else {
            return view('kasir.dashboard');
        }
    }
}
