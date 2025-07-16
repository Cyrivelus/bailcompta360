<?php

namespace Librairies\Abs2000Generator;

class Abs2000Generator
{
    private string $delimiter = ';'; // Délimiteur de champs par défaut pour ABS 2000 (souvent le point-virgule)
    private string $enclosure = '"'; // Caractère d'encapsulation des champs (si nécessaire)
    private string $lineEnding = "\r\n"; // Fin de ligne pour la compatibilité Windows (ABS 2000 pourrait fonctionner différemment)
    private string $charset = 'ISO-8859-1'; // Encodage de caractères potentiellement requis par ABS 2000

    private array $data = [];
    private array $headers = [];

    /**
     * Définit le délimiteur de champs pour le fichier CSV ABS 2000.
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
     * Définit le caractère d'encapsulation des champs pour le fichier CSV ABS 2000.
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
     * Définit la fin de ligne pour le fichier CSV ABS 2000.
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
     * Définit l'encodage des caractères pour le fichier CSV ABS 2000.
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
     * Définit les en-têtes des colonnes pour le fichier CSV.
     *
     * @param array $headers Un tableau de chaînes de caractères représentant les en-têtes.
     * @return $this
     */
    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * Ajoute une ligne de données au fichier CSV.
     *
     * @param array $row Un tableau de valeurs représentant une ligne de données.
     * @return $this
     */
    public function addRow(array $row): self
    {
        $this->data[] = $row;
        return $this;
    }

    /**
     * Ajoute plusieurs lignes de données au fichier CSV.
     *
     * @param array $data Un tableau multidimensionnel de données.
     * @return $this
     */
    public function addData(array $data): self
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    /**
     * Génère le contenu CSV pour ABS 2000.
     *
     * @return string Le contenu CSV formaté.
     */
    public function generate(): string
    {
        $output = '';

        // Ajouter les en-têtes si définis
        if (!empty($this->headers)) {
            $output .= $this->formatRow($this->headers) . $this->lineEnding;
        }

        // Ajouter les données
        foreach ($this->data as $row) {
            $output .= $this->formatRow($row) . $this->lineEnding;
        }

        // Convertir l'encodage si nécessaire
        if ($this->charset !== 'UTF-8') {
            $output = mb_convert_encoding($output, $this->charset, 'UTF-8');
        }

        return $output;
    }

    /**
     * Formate une ligne de données en une chaîne CSV.
     *
     * @param array $row Un tableau de valeurs.
     * @return string La ligne formatée en CSV.
     */
    private function formatRow(array $row): string
    {
        $formattedRow = [];
        foreach ($row as $value) {
            $formattedValue = str_replace($this->enclosure, $this->enclosure . $this->enclosure, $value);
            $formattedRow[] = $this->enclosure . $formattedValue . $this->enclosure;
        }
        return implode($this->delimiter, $formattedRow);
    }

    /**
     * Sauvegarde le contenu CSV dans un fichier.
     *
     * @param string $filename Le nom du fichier à sauvegarder.
     * @return bool True en cas de succès, false en cas d'échec.
     */
    public function save(string $filename): bool
    {
        $csvContent = $this->generate();
        return (bool) file_put_contents($filename, $csvContent);
    }

    /**
     * Force le téléchargement du fichier CSV par le navigateur.
     *
     * @param string $filename Le nom du fichier pour le téléchargement.
     * @return void
     */
    public function download(string $filename): void
    {
        $csvContent = $this->generate();

        header('Content-Type: text/csv; charset=' . $this->charset);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $csvContent;
        exit();
    }
}