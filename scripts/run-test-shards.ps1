param(
    [Parameter(Mandatory)][ValidateSet('sqlite','mariadb')][string]$Engine,
    [Parameter(Mandatory)][int]$Shards,
    [Parameter(Mandatory)][string]$OutDir,
    [string]$Filter = ''
)
# Launches the shards, WAITS for every phpunit process and records its real exit code
# (Process.ExitCode), then aggregates counts. Suite success = every exit code 0 AND no
# failures/errors in the summaries. PHPUnit exit 0 = pass, 1 = failures, 2 = errors.
$env:PATH = "G:\xampp\php;" + $env:PATH
Set-Location G:\xampp\htdocs\SocietyFlow
New-Item -ItemType Directory -Force -Path $OutDir | Out-Null
Get-ChildItem $OutDir -Filter "$Engine-*" -ErrorAction SilentlyContinue | Remove-Item -Force
$classes = Get-ChildItem tests -Recurse -Filter "*Test.php" | ForEach-Object { $_.BaseName } | Sort-Object -Unique
if ($Filter) { $classes = $classes | Where-Object { $_ -match $Filter } }
$groups = 0..($Shards - 1) | ForEach-Object { New-Object System.Collections.ArrayList }
for ($i = 0; $i -lt $classes.Count; $i++) { [void]$groups[$i % $Shards].Add($classes[$i]) }
foreach ($v in 'DB_CONNECTION','DB_DATABASE','DB_PORT','DB_HOST','DB_USERNAME','DB_PASSWORD') { Remove-Item "Env:$v" -ErrorAction SilentlyContinue }
$procs = @()
for ($n = 0; $n -lt $Shards; $n++) {
    if ($groups[$n].Count -eq 0) { continue }
    if ($Engine -eq 'mariadb') {
        $env:DB_CONNECTION = 'mysql'; $env:DB_HOST = '127.0.0.1'; $env:DB_PORT = '3307'
        $env:DB_DATABASE = "societyflow_test_$n"; $env:DB_USERNAME = 'root'; $env:DB_PASSWORD = ''
    }
    $pattern = '\\(' + ($groups[$n] -join '|') + ')::'
    $p = Start-Process -FilePath "php" -ArgumentList "vendor/bin/phpunit --filter `"$pattern`"" -WindowStyle Hidden -PassThru `
        -RedirectStandardOutput (Join-Path $OutDir "$Engine-$n.out.txt") -RedirectStandardError (Join-Path $OutDir "$Engine-$n.err.txt")
    $null = $p.Handle   # cache the handle so ExitCode is readable after exit
    $procs += [pscustomobject]@{ Shard = $n; Proc = $p }
}
$tot = @{ Tests = 0; Assertions = 0; Failures = 0; Errors = 0; Skipped = 0 }
$bad = 0
foreach ($x in $procs) {
    $x.Proc.WaitForExit(); $x.Proc.Refresh()
    $ec = $x.Proc.ExitCode
    $text = Get-Content (Join-Path $OutDir "$Engine-$($x.Shard).out.txt") -Raw
    $m = [regex]::Match($text, 'OK \((\d+) tests?, (\d+) assertions?\)')
    if ($m.Success) { $tot.Tests += [int]$m.Groups[1].Value; $tot.Assertions += [int]$m.Groups[2].Value }
    else {
        $m2 = [regex]::Match($text, 'Tests: (\d+), Assertions: (\d+)([^\r\n]*)')
        if ($m2.Success) {
            $tot.Tests += [int]$m2.Groups[1].Value; $tot.Assertions += [int]$m2.Groups[2].Value
            foreach ($k in 'Failures','Errors','Skipped') { $km = [regex]::Match($m2.Groups[3].Value, "$k`: (\d+)"); if ($km.Success) { $tot[$k] += [int]$km.Groups[1].Value } }
        } else { $ec = if ($ec -eq 0) { 99 } else { $ec } }   # no summary at all = failure
    }
    if ($ec -ne 0) { $bad++ }
    "shard $($x.Shard): exit=$ec"
}
"TOTAL tests=$($tot.Tests) assertions=$($tot.Assertions) failures=$($tot.Failures) errors=$($tot.Errors) skipped=$($tot.Skipped) shards=$($procs.Count) nonzero_exit_shards=$bad"
"RESULT: " + $(if ($bad -eq 0 -and $tot.Failures -eq 0 -and $tot.Errors -eq 0) { 'PASS (all exit codes 0)' } else { 'FAIL' })
exit $bad