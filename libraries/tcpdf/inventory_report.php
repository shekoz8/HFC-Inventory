<?php
require_once(__DIR__ . '/tcpdf.php');

class InventoryReport extends TCPDF {
    public function Header() {
        // Set margins
        $this->SetMargins(15, 15, 15);
        
        // Logo - using absolute path from root
        $logo_path = $_SERVER['DOCUMENT_ROOT'] . '/hfc_inventory/images/HFC-logo.png';
        if (file_exists($logo_path)) {
            $this->Image($logo_path, 15, 10, 30, '', 'PNG');
        }
        
        // Title
        $this->SetFont('helvetica', 'B', 20);
        $this->Cell(0, 15, 'HFC Inventory Report', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln();
        
        // Date
        $this->SetFont('helvetica', '', 12);
        $this->Cell(0, 10, date('F d, Y'), 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(25);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }

    public function AddTable($items) {
        // Add title and description
        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 10, 'Inventory Items', 0, 1, 'L');
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 5, 'Current inventory status and quantities', 0, 1, 'L');
        $this->Ln(15);

        // Add table headers
        $this->SetFont('helvetica', 'B', 12);
        $header = array('Item Name', 'Category', 'Quantity', 'Min Quantity', 'Status');
        $w = array(50, 35, 25, 30, 40);
        
        // Draw header row with background color
        $this->SetFillColor(41, 128, 185);
        $this->SetTextColor(255);
        $this->SetDrawColor(41, 128, 185);
        
        for($i=0;$i<count($header);$i++) {
            $this->Cell($w[$i], 10, $header[$i], 1, 0, 'C', true);
        }
        
        $this->Ln();
        
        // Reset colors
        $this->SetFillColor(224, 235, 255);
        $this->SetTextColor(0);
        $this->SetFont('helvetica', '', 11);
        
        // Add table data with alternating row colors
        $fill = false;
        foreach($items as $item) {
            $this->Cell($w[0], 8, $item['name'], 'LR', 0, 'L', $fill);
            $this->Cell($w[1], 8, $item['category_name'], 'LR', 0, 'L', $fill);
            $this->Cell($w[2], 8, $item['quantity'], 'LR', 0, 'R', $fill);
            $this->Cell($w[3], 8, $item['min_quantity'], 'LR', 0, 'R', $fill);
            $this->Cell($w[4], 8, $item['status'], 'LR', 0, 'C', $fill);
            $this->Ln();
            $fill = !$fill;
        }
        
        // Close table
        $this->Cell(array_sum($w), 0, '', 'T');
        
        // Add summary
        $this->Ln(15);
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 10, 'Report Summary', 0, 1, 'L');
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 5, 'Generated on: ' . date('F d, Y H:i:s'), 0, 1, 'L');
        $this->Cell(0, 5, 'Total items: ' . count($items), 0, 1, 'L');
    }
}
