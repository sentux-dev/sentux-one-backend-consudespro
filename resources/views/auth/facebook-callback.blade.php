<!DOCTYPE html>
<html>
<head>
    <title>Autorización de Facebook</title>
    <script>
        // Esta función cierra la ventana emergente automáticamente
        window.close();
    </script>
</head>
<body>
    @if($success)
        <p>¡Autorización completada! Esta ventana se cerrará automáticamente.</p>
    @else
        <p>Error: {{ $message ?? 'Ocurrió un error desconocido.' }}</p>
    @endif
</body>
</html>