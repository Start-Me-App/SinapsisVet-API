<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificado</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
            text-align: center;
        }
        .container {
            width: 800px;
            padding: 40px;
            background-color: #fff;
            margin: auto;
            border: 1px solid #ccc;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            background-color: #2a7277;
            color: #fff;
            padding: 10px;
            font-size: 24px;
            font-weight: bold;
        }
        .sub-header {
            color: #2a7277;
            font-style: italic;
            margin-top: -10px;
        }
        .content {
            margin: 30px 0;
            font-size: 18px;
        }
        .student-info {
            margin: 20px 0;
        }
        .signatures {
            display: flex;
            justify-content: space-around;
            margin-top: 40px;
        }
        .signatures div {
            text-align: center;
        }
        .logos {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        .logos img {
            height: 80px;
        }
        hr {
            width: 50%;
            margin: 10px auto;
            border: none;
            border-top: 1px solid #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">SINAPSIS VET</div>
        <div class="sub-header">Certificado</div>
        
        <div class="content">
            <p>Certifica que el alumno:</p>
            <div class="student-info">
                <p><strong>{{$student}}</strong> <span style="margin-left: 30px;">DNI <strong> 123456789  </strong></span></p>
                <hr>
            </div>
            <p>ha aprobado el Curso de:</p>
            <p><em>"{{$title}}"</em></p>
            <p>En condición de asistente, evento modalidad virtual.</p>
            <p>A cargo del Lic. Fernando pellegrino</p>
            <p>República Argentina, {{$date}}</p>
        </div>
        
        <div class="signatures">
            <div>
                <hr>
                <p>Prof. Sergio Larumbe</p>
                <p>Director de CER</p>
            </div>
           
        </div>
      
    </div>
</body>
</html>
