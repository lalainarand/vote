<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BulletinLog;
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
    /**
     * Nombre de sièges à pourvoir : les N premiers candidats classés sont élus.
     * Utilisé à la fois par l'affichage (Résultats/Index.vue) et l'export Excel,
     * pour que les deux documents restent strictement cohérents.
     */
    private const SEATS = 9;

    /**
     * Calcule l'intégralité des chiffres de la page Résultats (candidats, blanc/nul,
     * bulletins individuels/procuration, statuts des bureaux…) pour un scope donné.
     * Partagé par index() (affichage) et export() (Excel) afin que les deux ne
     * puissent jamais diverger.
     */
    private function buildReportData(string $scope): array
    {
        $statusCounts = BureauVote::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $totalBureaux     = BureauVote::count();
        $validatedBureaux = (int) ($statusCounts['validated'] ?? 0);

        $bureauIds = $scope === 'validated'
            ? BureauVote::where('status', 'validated')->pluck('id')
            : BureauVote::pluck('id');

        $options = VoteOption::orderBy('ordre_affichage')->get();

        $excludeReset = function ($query) {
            $query->where(function ($q) {
                $q->whereNull('is_reset')->orWhere('is_reset', false);
            });
        };

        // Le bulletin hérite du type de bureau qui l'a saisi (colonne is_procuration,
        // posée à la création par CountingController). On ne se base plus sur
        // is_manuel : ce flag ne distingue que saisie unitaire / groupée, pas le
        // type de bureau — les entrées correctives de reset étaient TOUJOURS
        // is_manuel=true, y compris pour un bureau individuel, ce qui les
        // classait à tort comme "procuration".
        $onlyProcuration = function ($query) {
            $query->where('is_procuration', true);
        };
        $onlyIndividuel = function ($query) {
            $query->where(function ($q) {
                $q->where('is_procuration', false)->orWhereNull('is_procuration');
            });
        };

        $results = $options->map(function ($option) use ($bureauIds, $excludeReset) {
            $plus = VoteLog::where('vote_option_id', $option->id)
                ->whereIn('bureau_vote_id', $bureauIds)->where('action', '+1')->tap($excludeReset)->sum('quantity');
            $minus = VoteLog::where('vote_option_id', $option->id)
                ->whereIn('bureau_vote_id', $bureauIds)->where('action', '-1')->tap($excludeReset)->sum('quantity');

            $systemCount = $plus - $minus;

            $procuration = VoteLog::where('vote_option_id', $option->id)
                ->whereIn('bureau_vote_id', $bureauIds)->where('is_procuration', true)->tap($excludeReset)->sum('quantity');

            $bureauResults = BureauResult::where('vote_option_id', $option->id)->whereIn('bureau_vote_id', $bureauIds)->get();
            $pvCount  = (int) $bureauResults->sum('count');

            return [
                'id'           => $option->id,
                'nom'          => $option->nom,
                'type'         => $option->type,
                'photo'        => $option->photo,
                'system_count' => $systemCount,
                'procuration'  => (int) $procuration,
                'pv_count'     => $pvCount,
                'ecart'        => $pvCount - $systemCount,
                'numero'       => $option->ordre_affichage,
            ];
        });

        $candidates = $results->where('type', 'candidat')->values();
        $others     = $results->where('type', '!=', 'candidat')->values();

        $candidatesRankedBySystem = $candidates->sortByDesc(fn($r) => (int) $r['system_count'])->values();

        $totalCandidatesPv          = $candidates->sum('pv_count');
        $totalCandidatesSystem      = $candidates->sum('system_count');
        $totalCandidatesProcuration = $candidates->sum('procuration');

        // Électeurs individuels (is_procuration = false ou NULL, quantity = 1 par bulletin)
        $electeursIndividuelsPlus = BulletinLog::whereIn('bureau_vote_id', $bureauIds)
            ->tap($onlyIndividuel)->where('action', '+1')->tap($excludeReset)->sum('quantity');
        $electeursIndividuelsMinus = BulletinLog::whereIn('bureau_vote_id', $bureauIds)
            ->tap($onlyIndividuel)->where('action', '-1')->tap($excludeReset)->sum('quantity');
        $totalElecteursIndividuels = (int) ($electeursIndividuelsPlus - $electeursIndividuelsMinus);

        // Électeurs par procuration (is_procuration = true, quantity = N électeurs représentés
        // par ce lot — une saisie peut regrouper plusieurs votants procurés en une fois)
        $electeursProcurationPlus = BulletinLog::whereIn('bureau_vote_id', $bureauIds)
            ->tap($onlyProcuration)->where('action', '+1')->tap($excludeReset)->sum('quantity');
        $electeursProcurationMinus = BulletinLog::whereIn('bureau_vote_id', $bureauIds)
            ->tap($onlyProcuration)->where('action', '-1')->tap($excludeReset)->sum('quantity');
        $totalElecteursProcuration = (int) ($electeursProcurationPlus - $electeursProcurationMinus);

        // Nombre de bulletins de procuration à proprement parler (nombre de saisies /
        // documents traités), distinct du nombre de votants qu'ils représentent :
        // 2 bulletins peuvent totaliser 98 votants procurés (ex: 50 + 48).
        $totalBulletinsProcurationCount = BulletinLog::whereIn('bureau_vote_id', $bureauIds)
            ->tap($onlyProcuration)->where('action', '+1')->tap($excludeReset)->count();

        $totalElecteurs = $totalElecteursIndividuels + $totalElecteursProcuration;

        $totalVoixIndividuelles = (int) VoteLog::whereIn('bureau_vote_id', $bureauIds)
            ->where('is_procuration', false)
            ->tap($excludeReset)
            ->sum('quantity');

        $totalVoixProcuration = (int) VoteLog::whereIn('bureau_vote_id', $bureauIds)
            ->where('is_procuration', true)
            ->tap($excludeReset)
            ->sum('quantity');

        $sourceBreakdown = DB::table('bureaux_vote')
            ->whereIn('bureaux_vote.id', $bureauIds)
            ->join('bureau_results', 'bureaux_vote.id', '=', 'bureau_results.bureau_vote_id')
            ->selectRaw('bureau_results.source, COUNT(DISTINCT bureaux_vote.id) as count')
            ->groupBy('bureau_results.source')
            ->pluck('count', 'source');

        return [
            'scope'                             => $scope,
            'results'                           => $results,
            'candidates'                        => $candidates,
            'others'                            => $others,
            'candidates_ranked_by_system'       => $candidatesRankedBySystem,

            'total_candidates_pv'               => $totalCandidatesPv,
            'total_candidates_system'           => $totalCandidatesSystem,
            'total_candidates_procuration'      => (int) $totalCandidatesProcuration,

            'total_electeurs'                   => $totalElecteurs,
            'total_electeurs_individuels'       => $totalElecteursIndividuels,
            'total_electeurs_procuration'       => $totalElecteursProcuration,
            'total_bulletins_procuration_count' => $totalBulletinsProcurationCount,

            'total_voix_individuelles'          => $totalVoixIndividuelles,
            'total_voix_procuration'            => $totalVoixProcuration,

            'validated_bureaux'                 => $validatedBureaux,
            'total_bureaux'                      => $totalBureaux,
            'source_breakdown'                  => $sourceBreakdown,
            'status_counts'                      => $statusCounts,
        ];
    }

    public function index(Request $request)
    {
        $scope = $request->query('scope', 'all');
        $data  = $this->buildReportData($scope);

        return Inertia::render('Admin/Resultats/Index', [
            'results'                           => $data['results'],
            'total_candidates_pv'               => $data['total_candidates_pv'],
            'total_candidates_system'           => $data['total_candidates_system'],
            'total_candidates_procuration'      => $data['total_candidates_procuration'],

            'total_electeurs'                   => $data['total_electeurs'],
            'total_electeurs_individuels'       => $data['total_electeurs_individuels'],
            'total_electeurs_procuration'       => $data['total_electeurs_procuration'],
            'total_bulletins_procuration_count' => $data['total_bulletins_procuration_count'],

            'total_voix_individuelles'          => $data['total_voix_individuelles'],
            'total_voix_procuration'            => $data['total_voix_procuration'],

            'validated_bureaux'                 => $data['validated_bureaux'],
            'total_bureaux'                      => $data['total_bureaux'],
            'source_breakdown'                  => $data['source_breakdown'],
            'status_counts'                      => $data['status_counts'],
            'scope'                             => $scope,
            'seats'                              => self::SEATS,
        ]);
    }

    public function export(Request $request)
    {
        $scope = $request->query('scope', 'all');
        $data  = $this->buildReportData($scope);

        $ranked      = $data['candidates_ranked_by_system'];
        $others      = $data['others'];
        $totalSystem = (int) $ranked->sum('system_count');
        $seats       = self::SEATS;

        // ── Construction du fichier Excel ──
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Résultats');

        // 1. Titre principal
        $sheet->setCellValue('A1', 'Résultats globaux — ' . ($scope === 'validated' ? 'Bureaux validés' : 'Tous les bureaux'));
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Généré le ' . now()->format('d/m/Y H:i'));
        $sheet->mergeCells('A2:F2');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('666666');

        // 2. 📊 RÉSUMÉ DE LA SITUATION
        $summaryRow = 4;
        $sheet->setCellValue('A' . $summaryRow, 'RÉSUMÉ DE LA SITUATION');
        $sheet->mergeCells('A' . $summaryRow . ':F' . $summaryRow);
        $sheet->getStyle('A' . $summaryRow)->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('1F2937');
        $sheet->getStyle('A' . $summaryRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $r = $summaryRow + 1;

        // Ligne 1 : Bureaux
        $sheet->setCellValue('A' . $r, 'Total des bureaux :');
        $sheet->setCellValue('B' . $r, $data['total_bureaux']);
        $sheet->setCellValue('C' . $r, 'Bureaux validés :');
        $sheet->setCellValue('D' . $r, $data['validated_bureaux']);
        $sheet->getStyle('A' . $r . ':D' . $r)->getFont()->setBold(true);
        $r++;

        // Ligne 2 : Détail des statuts
        $sheet->setCellValue('A' . $r, 'Détail des statuts :');
        $statusLabels = [
            'pending'   => 'En attente',
            'counting'  => 'Comptage',
            'anomaly'   => 'Anomalie',
            'validated' => 'Validé',
        ];
        $statusText = [];
        foreach ($statusLabels as $key => $label) {
            if (isset($data['status_counts'][$key])) {
                $statusText[] = "{$label}: {$data['status_counts'][$key]}";
            }
        }
        $sheet->setCellValue('B' . $r, implode(' | ', $statusText));
        $sheet->mergeCells('B' . $r . ':F' . $r);
        $r++;

        // Ligne 3 : Sources des PV
        $sheet->setCellValue('A' . $r, 'Sources des PV :');
        $sourceText = [
            'Opérateur: ' . ((int) ($data['source_breakdown']['counting'] ?? 0)),
            'Admin (PV): ' . ((int) ($data['source_breakdown']['manual_pv'] ?? 0)),
            'Admin (Override): ' . ((int) ($data['source_breakdown']['admin_override'] ?? 0)),
        ];
        $sheet->setCellValue('B' . $r, implode(' | ', $sourceText));
        $sheet->mergeCells('B' . $r . ':F' . $r);
        $r++;

        // Ligne 4 : Bulletins individuels / procuration / total des votants
        $sheet->setCellValue('A' . $r, 'Bulletins individuels :');
        $sheet->setCellValue('B' . $r, $data['total_electeurs_individuels']);
        $sheet->setCellValue('C' . $r, 'Votants par procuration :');
        $sheet->setCellValue('D' . $r, $data['total_electeurs_procuration']);
        $sheet->setCellValue(
            'E' . $r,
            'via ' . $data['total_bulletins_procuration_count'] . ' bulletin(s) de procuration'
        );
        $sheet->mergeCells('E' . $r . ':F' . $r);
        $sheet->getStyle('A' . $r . ':D' . $r)->getFont()->setBold(true);
        $sheet->getStyle('E' . $r)->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('666666');
        $r++;

        $sheet->setCellValue('A' . $r, 'Total des votants :');
        $sheet->setCellValue('B' . $r, $data['total_electeurs']);
        $sheet->setCellValue('C' . $r, '(= bulletins individuels + votants par procuration)');
        $sheet->mergeCells('C' . $r . ':F' . $r);
        $sheet->getStyle('A' . $r . ':B' . $r)->getFont()->setBold(true);
        $sheet->getStyle('C' . $r)->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('666666');
        $r++;

        // 3. En-têtes du tableau de résultats candidats
        // (nombre de voix = compteur système, utilisé par défaut)
        $headerRow = $r + 2;
        $headers = ['Classement', 'Statut', 'N°', 'Candidat', 'Nombre de voix', 'Pourcentage'];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col . $headerRow, $h);
            $col++;
        }

        $sheet->getStyle('A' . $headerRow . ':F' . $headerRow)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A' . $headerRow . ':F' . $headerRow)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F2937');
        $sheet->getStyle('A' . $headerRow . ':F' . $headerRow)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // 4. Lignes candidats — les `seats` premiers sont mis en valeur (élus)
        $row = $headerRow + 1;
        $firstDataRow = $row;

        foreach ($ranked as $index => $rData) {
            $isElected = $index < $seats;

            $sheet->setCellValue('A' . $row, $index + 1);
            $sheet->setCellValue('B' . $row, $isElected ? 'Élu' : '—');
            $sheet->setCellValue('C' . $row, $rData['numero'] ?? '');
            $sheet->setCellValue('D' . $row, $rData['nom']);
            $sheet->setCellValue('E' . $row, (int) $rData['system_count']);

            $pct = $totalSystem > 0 ? ((float) $rData['system_count'] / (float) $totalSystem) : 0.0;
            $sheet->setCellValue('F' . $row, $pct);

            if ($isElected) {
                $sheet->getStyle('A' . $row . ':F' . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCFCE7'); // vert clair
                $sheet->getStyle('B' . $row)->getFont()->setBold(true)->getColor()->setRGB('15803D');
            }

            $row++;
        }
        $lastDataRow = $row - 1;

        // Format pourcentage
        $sheet->getStyle('F' . $firstDataRow . ':F' . $lastDataRow)
            ->getNumberFormat()->setFormatCode('0.00%');

        // Bordures et alignements
        $sheet->getStyle('A' . $headerRow . ':F' . $lastDataRow)
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A' . $firstDataRow . ':C' . $lastDataRow)
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('E' . $firstDataRow . ':F' . $lastDataRow)
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // 5. Blanc / Nul
        if ($others->isNotEmpty()) {
            $othersHeaderRow = $lastDataRow + 3;
            $sheet->setCellValue('A' . $othersHeaderRow, 'Bulletins blancs et nuls');
            $sheet->mergeCells('A' . $othersHeaderRow . ':F' . $othersHeaderRow);
            $sheet->getStyle('A' . $othersHeaderRow)->getFont()->setBold(true)->setSize(11);

            $oRow = $othersHeaderRow + 1;
            $sheet->setCellValue('D' . $oRow, 'Type');
            $sheet->setCellValue('E' . $oRow, 'Nombre de voix');
            $sheet->getStyle('D' . $oRow . ':E' . $oRow)->getFont()->setBold(true);
            $oRow++;

            foreach ($others as $rData) {
                $sheet->setCellValue('D' . $oRow, $rData['nom']);
                $sheet->setCellValue('E' . $oRow, (int) $rData['system_count']);
                $oRow++;
            }
            $sheet->getStyle('D' . $othersHeaderRow . ':E' . ($oRow - 1))
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        // Largeur colonnes
        foreach (['A', 'B', 'C', 'E', 'F'] as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }
        $sheet->getColumnDimension('D')->setWidth(35);

        $filename = 'resultats_' . ($scope === 'validated' ? 'valides_' : '') . date('Y-m-d_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
