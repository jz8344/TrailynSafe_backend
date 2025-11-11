# Script de PowerShell para probar API de admin
Write-Host "=== PROBANDO API DE ADMIN ===" -ForegroundColor Green

# Función para hacer petición HTTP
function Test-ApiEndpoint {
    param(
        [string]$Url,
        [string]$Method = "GET",
        [hashtable]$Body = @{},
        [hashtable]$Headers = @{}
    )
    
    try {
        $response = Invoke-RestMethod -Uri $Url -Method $Method -Body ($Body | ConvertTo-Json) -Headers $Headers -ContentType "application/json"
        Write-Host "✓ $Url - OK" -ForegroundColor Green
        return $response
    } catch {
        Write-Host "✗ $Url - Error: $($_.Exception.Message)" -ForegroundColor Red
        return $null
    }
}

# Probar endpoint de test
Write-Host "`nProbando endpoint de test..." -ForegroundColor Yellow
$testResponse = Test-ApiEndpoint -Url "http://localhost:8000/api/admin/test"

if ($testResponse) {
    Write-Host "Respuesta del test:" -ForegroundColor Cyan
    $testResponse | ConvertTo-Json -Depth 3 | Write-Host
    
    # Si el test funciona, probar login
    Write-Host "`nProbando login de admin..." -ForegroundColor Yellow
    $loginData = @{
        email = "admin@trailynsafe.com"
        password = "admin123"
    }
    
    $loginResponse = Test-ApiEndpoint -Url "http://localhost:8000/api/admin/login" -Method "POST" -Body $loginData
    
    if ($loginResponse -and $loginResponse.token) {
        Write-Host "✓ Login exitoso. Token obtenido." -ForegroundColor Green
        
        # Probar endpoint protegido
        Write-Host "`nProbando endpoint protegido..." -ForegroundColor Yellow
        $authHeaders = @{
            "Authorization" = "Bearer $($loginResponse.token)"
        }
        
        $usersResponse = Test-ApiEndpoint -Url "http://localhost:8000/api/admin/usuarios" -Headers $authHeaders
        
        if ($usersResponse) {
            Write-Host "✓ Endpoint protegido funciona correctamente" -ForegroundColor Green
        }
    }
} else {
    Write-Host "No se pudo conectar al servidor. Asegúrate de que Laravel esté ejecutándose en localhost:8000" -ForegroundColor Red
}

Write-Host "`n=== FIN DE PRUEBAS ===" -ForegroundColor Green
