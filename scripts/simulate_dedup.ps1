$SqlFile = 'c:\xampp\htdocs\dev\uf_gestion\a0011086_erp_mvp_fulldb.sql'
try {
    $sql = Get-Content -Path $SqlFile -Raw -Encoding UTF8 -ErrorAction Stop
} catch {
    Write-Error "No pude leer el archivo: $_"
    exit 1
}

$pattern = "INSERT INTO `(?<table>[^`]+)`\s*\((?<cols>[^)]+)\)\s*VALUES\s*(?<vals>.*?);"
$m = [regex]::Matches($sql, $pattern, [System.Text.RegularExpressions.RegexOptions]::Singleline)

$results = @{}
$totalDeletes = 0

function Parse-Tuples([string]$valuesText){
    $tuples = @()
    $i = 0
    $n = $valuesText.Length
    while ($i -lt $n) {
        while ($i -lt $n -and $valuesText[$i] -ne '(') { $i++ }
        if ($i -ge $n) { break }
        $i++
        $start = $i
        $depth = 1
        $inQuote = $false
        $quoteChar = ''
        while ($i -lt $n -and $depth -gt 0) {
            $ch = $valuesText[$i]
            if ($inQuote) {
                if ($ch -eq $quoteChar) {
                    # handle doubled quotes
                    if ($i+1 -lt $n -and $valuesText[$i+1] -eq $quoteChar) { $i++ }
                    else { $inQuote = $false }
                } elseif ($ch -eq '\\') { $i++ }
            } else {
                if ($ch -eq '"' -or $ch -eq "'") { $inQuote = $true; $quoteChar = $ch }
                elseif ($ch -eq '(') { $depth++ }
                elseif ($ch -eq ')') { $depth-- }
            }
            $i++
        }
        $end = $i-1
        $tupleText = $valuesText.Substring($start, $end - $start).Trim()
        $tuples += $tupleText
    }
    return $tuples
}

function Split-TopCommas([string]$s){
    $parts = @()
    $cur = New-Object System.Text.StringBuilder
    $inQuote = $false
    $quoteChar = ''
    for ($i=0; $i -lt $s.Length; $i++){
        $ch = $s[$i]
        if ($inQuote) {
            $cur.Append($ch) | Out-Null
            if ($ch -eq $quoteChar) {
                if ($i+1 -lt $s.Length -and $s[$i+1] -eq $quoteChar) { $cur.Append($s[$i+1]) | Out-Null; $i++ }
                else { $inQuote = $false }
            }
        } else {
            if ($ch -eq '"' -or $ch -eq "'") { $inQuote = $true; $quoteChar = $ch; $cur.Append($ch) | Out-Null }
            elseif ($ch -eq ',') { $parts += $cur.ToString().Trim(); $cur.Clear() | Out-Null }
            else { $cur.Append($ch) | Out-Null }
        }
    }
    if ($cur.Length -gt 0) { $parts += $cur.ToString().Trim() }
    return $parts
}

function Normalize-Value([string]$v){
    $vv = $v.Trim()
    if ($vv.ToUpper() -eq 'NULL') { return '<NULL>' }
    if ((($vv.StartsWith("'")) -and ($vv.EndsWith("'"))) -or (($vv.StartsWith('"')) -and ($vv.EndsWith('"')))){
        $vv = $vv.Substring(1, $vv.Length-2)
        $vv = $vv -replace "\\r", "\\r"
        $vv = $vv -replace "\\n", "\\n"
        $vv = $vv -replace "''", "'"
    }
    return $vv
}

foreach ($match in $m) {
    $table = $match.Groups['table'].Value
    $cols = ($match.Groups['cols'].Value -split ',') | ForEach-Object { $_.Trim().Trim('`') }
    $valsText = $match.Groups['vals'].Value
    $tuples = Parse-Tuples $valsText
    $keymap = @{}

    $id_idx = -1
    for ($i=0; $i -lt $cols.Length; $i++){
        if ($cols[$i] -eq 'id') { $id_idx = $i; break }
    }
    foreach ($t in $tuples){
        $cols_vals = Split-TopCommas $t
        if ($cols_vals.Count -ne $cols.Length) { continue }
        if ($id_idx -lt 0) {
            $keyParts = $cols_vals | ForEach-Object { Normalize-Value $_ }
            $key = [string]::Join('||',$keyParts)
            if (-not $keymap.ContainsKey($key)) { $keymap[$key] = @() }
            $keymap[$key] += $null
        } else {
            $idVal = Normalize-Value $cols_vals[$id_idx]
            $parts = @()
            for ($j=0; $j -lt $cols_vals.Count; $j++){ if ($j -ne $id_idx) { $parts += Normalize-Value $cols_vals[$j] } }
            $key = [string]::Join('||',$parts)
            if (-not $keymap.ContainsKey($key)) { $keymap[$key] = @() }
            $keymap[$key] += $idVal
        }
    }
    $rows = 0
    $groups_dup = 0
    $deletes = 0
    foreach ($k in $keymap.Keys){
        $count = $keymap[$k].Count
        $rows += $count
        if ($count -gt 1) { $groups_dup++; $deletes += ($count - 1) }
    }
    $results[$table] = @{ rows = $rows; duplicate_groups = $groups_dup; deletes = $deletes }
    $totalDeletes += $deletes
}

Write-Output "Simulación de deduplicado (conservar id menor)."
Write-Output "Archivo: $SqlFile`n"
Write-Output ("{0,-40} {1,10} {2,15} {3,10}" -f 'Tabla','Filas','Grupos dup','A eliminar')
Write-Output ('-'*80)
foreach ($t in ($results.Keys | Sort-Object)){
    $info = $results[$t]
    Write-Output ("{0,-40} {1,10} {2,15} {3,10}" -f $t, $info.rows, $info.duplicate_groups, $info.deletes)
}
Write-Output ('-'*80)
Write-Output "Total filas a eliminar estimadas: $totalDeletes"
