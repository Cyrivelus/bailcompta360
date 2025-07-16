<?php

namespace Librairies\CsvParser;

class CsvParser
{
    private string $delimiter = ','; // Délimiteur de champs par défaut
    private string $enclosure = '"'; // Caractère d'encapsulation par défaut
    private string $escape = '\\';    // Caractère d'échappement par défaut
    private string $lineEnding = "\n"; // Fin de ligne par défaut
    private string $charset = 'UTF-8'; // Encodage de caractères par défaut
    private bool $hasHeader = true;   // Indique si la première ligne contient les en-têtes
    private array $header = [];       // Tableau pour stocker les en-têtes
    private array $data = [];         // Tableau pour stocker les données parsées

    /**
     * Définit le délimiteur de champs pour le fichier CSV.
     *
     * @param string $delimiter
     * @return $this
     */
    public function setDelimiter(string $delimiter): self
    {
        $this->delimiter = $delimiter;
        return $this;
    }

    /**
     * Définit le caractère d'encapsulation des champs pour le fichier CSV.
     *
     * @param string $enclosure
     * @return $this
     */
    public function setEnclosure(string $enclosure): self
    {
        $this->enclosure = $enclosure;
        return $this;
    }

    /**
     * Définit le caractère d'échappement pour le fichier CSV.
     *
     * @param string $escape
     * @return $this
     */
    public function setEscape(string $escape): self
    {
        $this->escape = $escape;
        return $this;
    }

    /**
     * Définit la fin de ligne pour le fichier CSV.
     *
     * @param string $lineEnding
     * @return $this
     */
    public function setLineEnding(string $lineEnding): self
    {
        $this->lineEnding = $lineEnding;
        return $this;
    }

    /**
     * Définit l'encodage des caractères du fichier CSV.
     *
     * @param string $charset
     * @return $this
     */
    public function setCharset(string $charset): self
    {
        $this->charset = $charset;
        return $this;
    }

    /**
     * Indique si la première ligne du CSV contient les en-têtes.
     *
     * @param bool $hasHeader
     * @return $this
     */
    public function setHasHeader(bool $hasHeader): self
    {
        $this->hasHeader = $hasHeader;
        return $this;
    }

    /**
     * Parse un fichier CSV.
     *
     * @param string $filename Le chemin vers le fichier CSV.
     * @return bool True en cas de succès, false en cas d'échec de la lecture du fichier.
     */
    public function parseFile(string $filename): bool
    {
        if (!is_readable($filename)) {
            error_log("Le fichier CSV '$filename' n'est pas lisible.");
            return false;
        }

        $handle = fopen($filename, 'r');
        if ($handle === false) {
            error_log("Impossible d'ouvrir le fichier CSV '$filename'.");
            return false;
        }

        $this->data = [];
        $rowNumber = 0;

        while (($row = fgetcsv($handle, 0, $this->delimiter, $this->enclosure, $this->escape)) !== false) {
            // Convertir l'encodage si nécessaire
            if ($this->charset !== 'UTF-8') {
                $encodedRow = [];
                foreach ($row as $value) {
                    $encodedRow[] = mb_convert_encoding($value, 'UTF-8', $this->charset);
                }
                $row = $encodedRow;
            }

            if ($rowNumber === 0 && $this->hasHeader) {
                $this->header = $row;
            } else {
                $this->data[] = $row;
            }
            $rowNumber++;
        }

        fclose($handle);
        return true;
    }

    /**
     * Parse une chaîne de caractères CSV.
     *
     * @param string $csvString La chaîne de caractères contenant les données CSV.
     * @return void
     */
    public function parseString(string $csvString): void
    {
        $this->data = [];
        $lines = explode($this->lineEnding, $csvString);
        $rowNumber = 0;

        foreach ($lines as $line) {
            $row = str_getcsv($line, $this->delimiter, $this->enclosure, $this->escape);
            if ($row !== false) {
                // Convertir l'encodage si nécessaire
                if ($this->charset !== 'UTF-8') {
                    $encodedRow = [];
                    foreach ($row as $value) {
                        $encodedRow[] = mb_convert_encoding($value, 'UTF-8', $this->charset);
                    }
                    $row = $encodedRow;
                }

                if ($rowNumber === 0 && $this->hasHeader) {
                    $this->header = $row;
                } else {
                    $this->data[] = $row;
                }
                $rowNumber++;
            }
        }
    }

    /**
     * Retourne les en-têtes du fichier CSV (si l'option hasHeader est activée).
     *
     * @return array
     */
    public function getHeader(): array
    {
        return $this->header;
    }

    /**
     * Retourne les données parsées du fichier CSV sous forme de tableau associatif (si un en-tête existe).
     * Chaque ligne est un tableau associatif où les clés sont les en-têtes.
     * Si aucun en-tête n'existe, retourne un tableau de tableaux numériques.
     *
     * @return array
     */
    public function getData(): array
    {
        if (!empty($this->header) && !empty($this->data)) {
            $associativeData = [];
            foreach ($this->data as $row) {
                $associativeRow = [];
                $numHeaders = count($this->header);
                $numValues = count($row);
                for ($i = 0; $i < max($numHeaders, $numValues); $i++) {
                    $headerKey = $this->header[$i] ?? $i; // Utiliser l'index si l'en-tête n'existe pas
                    $value = $row[$i] ?? null;           // Gérer le cas où la ligne a moins de colonnes que l'en-tête
                    $associativeRow[$headerKey] = $value;
                }
                $associativeData[] = $associativeRow;
            }
            return $associativeData;
        }
        return $this->data;
    }

    /**
     * Retourne les données parsées brutes sous forme de tableau de tableaux numériques.
     *
     * @return array
     */
    public function getRawData(): array
    {
        return $this->data;
    }
}