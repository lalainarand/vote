<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BureauResult;
use App\Models\BureauVote;
use App\Models\VoteLog;
use App\Models\VoteOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ResultController extends Controller
{
    public function index(Request $request)
    {
        // Scope : 'all' (défaut) inclut tous les statuts, 'validated' ne garde que les validés
        $scope = $request->query('scope', 'all');

        $statusCounts = BureauVote::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $totalBureaux     = BureauVote::count();
        $validatedBureaux = (int) ($statusCounts['validated'] ?? 0);

        $bureauIds = $scope === 'validated'
            ? BureauVote::where('status', 'validated')->pluck('id')
            : BureauVote::pluck('id'); // tous statuts confondus

        $options = VoteOption::orderBy('ordre_affichage')->get();

        $results = $options->map(function ($option) use ($bureauIds) {

            // --- Compteur système (VoteLogs, quantity incluse pour les procurations) ---
            $plus  = VoteLog::where('vote_option_id', $option->id)
                ->whereIn('bureau_vote_id', $bureauIds)
                ->where('action', '+1')
                ->sum('quantity');
            $minus = VoteLog::where('vote_option_id', $option->id)
                ->whereIn('bureau_vote_id', $bureauIds)
                ->where('action', '-1')
                ->sum('quantity');
            $systemCount = $plus - $minus;

            // --- Procuration ---
            $procuration = VoteLog::where('vote_option_id', $option->id)
                ->whereIn('bureau_vote_id', $bureauIds)
                ->where('is_procuration', true)
                ->sum('quantity');

            // --- PV papier (BureauResults) avec répartition par source ---
            $bureauResults = BureauResult::where('vote_option_id', $option->id)
                ->whereIn('bureau_vote_id', $bureauIds)
                ->get();

            $pvCount  = (int) $bureauResults->sum('count');
            $bySource = [
                'counting'       => (int) $bureauResults->where('source', 'counting')->sum('count'),
                'manual_pv'      => (int) $bureauResults->where('source', 'manual_pv')->sum('count'),
                'admin_override' => (int) $bureauResults->where('source', 'admin_override')->sum('count'),
            ];

            return [
                'id'           => $option->id,
                'nom'          => $option->nom,
                'type'         => $option->type,
                'system_count' => $systemCount,
                'procuration'  => (int) $procuration,
                'pv_count'     => $pvCount,
                'ecart'        => $pvCount - $systemCount,
                'by_source'    => $bySource,
                'numero'       => $option->ordre_affichage,
            ];
        });

        $totalCandidatesPv          = $results->where('type', 'candidat')->sum('pv_count');
        $totalCandidatesSystem      = $results->where('type', 'candidat')->sum('system_count');
        $totalCandidatesProcuration = $results->where('type', 'candidat')->sum('procuration');

        $totalProcuration = VoteLog::whereIn('bureau_vote_id', $bureauIds)
            ->where('is_procuration', true)
            ->sum('quantity');

        $sourceBreakdown = BureauVote::whereIn('bureaux_vote.id', $bureauIds)
            ->join('bureau_results', 'bureaux_vote.id', '=', 'bureau_results.bureau_vote_id')
            ->selectRaw('bureau_results.source, COUNT(DISTINCT bureaux_vote.id) as count')
            ->groupBy('bureau_results.source')
            ->pluck('count', 'source');

        return Inertia::render('Admin/Resultats/Index', [
            'results'                      => $results,
            'total_candidates_pv'          => $totalCandidatesPv,
            'total_candidates_system'      => $totalCandidatesSystem,
            'total_candidates_procuration' => (int) $totalCandidatesProcuration,
            'total_procuration'            => (int) $totalProcuration,
            'validated_bureaux'            => $validatedBureaux,
            'total_bureaux'                => $totalBureaux,
            'source_breakdown'             => $sourceBreakdown,
            'status_counts'                => $statusCounts,
            'scope'                        => $scope,
        ]);
    }


    public function export(Request $request)
    {
        $scope = $request->query('scope', 'all');

        $bureauIds = $scope === 'validated'
            ? BureauVote::where('status', 'validated')->pluck('id')
            : BureauVote::pluck('id');

        //  Récupération des statistiques pour le résumé
        $statusCounts = BureauVote::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $totalBureaux = BureauVote::count();
        $validatedBureaux = (int) ($statusCounts['validated'] ?? 0);

        $sourceBreakdown = \Illuminate\Support\Facades\DB::table('bureaux_vote')
            ->whereIn('bureaux_vote.id', $bureauIds)
            ->join('bureau_results', 'bureaux_vote.id', '=', 'bureau_results.bureau_vote_id')
            ->selectRaw('bureau_results.source, COUNT(DISTINCT bureaux_vote.id) as count')
            ->groupBy('bureau_results.source')
            ->pluck('count', 'source');

        $options = VoteOption::orderBy('ordre_affichage')->get();

        // ─ Calcul des résultats ──
        $results = $options->map(function ($option) use ($bureauIds) {
            $plus  = VoteLog::where('vote_option_id', $option->id)
                ->whereIn('bureau_vote_id', $bureauIds)
                ->where('action', '+1')
                ->sum('quantity');
            $minus = VoteLog::where('vote_option_id', $option->id)
                ->whereIn('bureau_vote_id', $bureauIds)
                ->where('action', '-1')
                ->sum('quantity');

            $systemCount = (int) ($plus - $minus);

            $procuration = (int) VoteLog::where('vote_option_id', $option->id)
                ->whereIn('bureau_vote_id', $bureauIds)
                ->where('is_procuration', true)
                ->sum('quantity');

            $pvCount = (int) BureauResult::where('vote_option_id', $option->id)
                ->whereIn('bureau_vote_id', $bureauIds)
                ->sum('count');

            return [
                'numero'       => $option->ordre_affichage,
                'nom'          => $option->nom,
                'type'         => $option->type,
                'system_count' => $systemCount,
                'procuration'  => $procuration,
                'pv_count'     => $pvCount,
                'ecart'        => $pvCount - $systemCount,
            ];
        });

        $candidates = $results->where('type', 'candidat')->values();
        $others     = $results->where('type', '!=', 'candidat')->values();

        $ranked = $candidates->sortByDesc(fn($r) => (int) $r['system_count'])->values();
        $totalSystem = (int) $ranked->sum('system_count');

        // ── Construction du fichier Excel ──
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Résultats');

        // 1. Titre principal
        $sheet->setCellValue('A1', 'Résultats globaux — ' . ($scope === 'validated' ? 'Bureaux validés' : 'Tous les bureaux'));
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Généré le ' . now()->format('d/m/Y H:i'));
        $sheet->mergeCells('A2:H2');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('666666');

        // 2. 📊 RÉSUMÉ DES STATUTS (nouvelle section)
        $summaryRow = 4;
        $sheet->setCellValue('A' . $summaryRow, 'RÉSUMÉ DE LA SITUATION');
        $sheet->mergeCells('A' . $summaryRow . ':H' . $summaryRow);
        $sheet->getStyle('A' . $summaryRow)->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('1F2937');
        $sheet->getStyle('A' . $summaryRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $r = $summaryRow + 1;

        // Ligne 1 : Totaux
        $sheet->setCellValue('A' . $r, 'Total des bureaux :');
        $sheet->setCellValue('B' . $r, $totalBureaux);
        $sheet->setCellValue('C' . $r, 'Bureaux validés :');
        $sheet->setCellValue('D' . $r, $validatedBureaux);
        $sheet->getStyle('A' . $r . ':D' . $r)->getFont()->setBold(true);

        $r++;

        // Ligne 2 : Détail des statuts
        $sheet->setCellValue('A' . $r, 'Détail des statuts :');
        $statusLabels = [
            'pending' => 'En attente',
            'counting' => 'Comptage',
            'anomaly' => 'Anomalie',
            'validated' => 'Validé'
        ];
        $statusText = [];
        foreach ($statusLabels as $key => $label) {
            if (isset($statusCounts[$key])) {
                $statusText[] = "{$label}: {$statusCounts[$key]}";
            }
        }
        $sheet->setCellValue('B' . $r, implode(' | ', $statusText));
        $sheet->mergeCells('B' . $r . ':H' . $r);

        $r++;

        // Ligne 3 : Sources des PV
        $sheet->setCellValue('A' . $r, 'Sources des PV :');
        $sourceText = [
            'Opérateur: ' . ((int) ($sourceBreakdown['counting'] ?? 0)),
            'Admin (PV): ' . ((int) ($sourceBreakdown['manual_pv'] ?? 0)),
            'Admin (Override): ' . ((int) ($sourceBreakdown['admin_override'] ?? 0))
        ];
        $sheet->setCellValue('B' . $r, implode(' | ', $sourceText));
        $sheet->mergeCells('B' . $r . ':H' . $r);

        // 3. En-têtes du tableau de résultats
        $headerRow = $r + 2;
        $headers = ['Classement', 'N°', 'Candidat', 'Votes système', 'Procuration', 'Votes PV', 'Écart', 'Pourcentage'];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col . $headerRow, $h);
            $col++;
        }

        $sheet->getStyle('A' . $headerRow . ':H' . $headerRow)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A' . $headerRow . ':H' . $headerRow)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F2937');
        $sheet->getStyle('A' . $headerRow . ':H' . $headerRow)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // 4. Lignes candidats
        $row = $headerRow + 1;
        $firstDataRow = $row;

        foreach ($ranked as $index => $rData) {
            $sheet->setCellValue('A' . $row, $index + 1);
            $sheet->setCellValue('B' . $row, $rData['numero'] ?? '');
            $sheet->setCellValue('C' . $row, $rData['nom']);
            $sheet->setCellValue('D' . $row, (int) $rData['system_count']);
            $sheet->setCellValue('E' . $row, (int) $rData['procuration']);
            $sheet->setCellValue('F' . $row, (int) $rData['pv_count']);
            $sheet->setCellValue('G' . $row, (int) $rData['ecart']);

            $pct = $totalSystem > 0 ? ((float) $rData['system_count'] / (float) $totalSystem) : 0.0;
            $sheet->setCellValue('H' . $row, $pct);

            $row++;
        }
        $lastDataRow = $row - 1;

        // Format pourcentage
        $sheet->getStyle('H' . $firstDataRow . ':H' . $lastDataRow)
            ->getNumberFormat()->setFormatCode('0.00%');

        // Bordures et alignements
        $sheet->getStyle('A' . $headerRow . ':H' . $lastDataRow)
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A' . $firstDataRow . ':B' . $lastDataRow)
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D' . $firstDataRow . ':H' . $lastDataRow)
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // 5. Blanc / Nul
        if ($others->isNotEmpty()) {
            $othersHeaderRow = $lastDataRow + 3;
            $sheet->setCellValue('A' . $othersHeaderRow, 'Bulletins blancs et nuls');
            $sheet->mergeCells('A' . $othersHeaderRow . ':H' . $othersHeaderRow);
            $sheet->getStyle('A' . $othersHeaderRow)->getFont()->setBold(true)->setSize(11);

            $oRow = $othersHeaderRow + 1;
            $sheet->setCellValue('C' . $oRow, 'Type');
            $sheet->setCellValue('D' . $oRow, 'Votes système');
            $sheet->setCellValue('E' . $oRow, 'Procuration');
            $sheet->setCellValue('F' . $oRow, 'Votes PV');
            $sheet->setCellValue('G' . $oRow, 'Écart');
            $sheet->getStyle('C' . $oRow . ':G' . $oRow)->getFont()->setBold(true);
            $oRow++;

            foreach ($others as $rData) {
                $sheet->setCellValue('C' . $oRow, $rData['nom']);
                $sheet->setCellValue('D' . $oRow, (int) $rData['system_count']);
                $sheet->setCellValue('E' . $oRow, (int) $rData['procuration']);
                $sheet->setCellValue('F' . $oRow, (int) $rData['pv_count']);
                $sheet->setCellValue('G' . $oRow, (int) $rData['ecart']);
                $oRow++;
            }
            $sheet->getStyle('C' . $othersHeaderRow . ':G' . ($oRow - 1))
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        // Largeur colonnes
        foreach (['A', 'B', 'D', 'E', 'F', 'G', 'H'] as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }
        $sheet->getColumnDimension('C')->setWidth(35);

        $filename = 'resultats_' . ($scope === 'validated' ? 'valides_' : '') . date('Y-m-d_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
