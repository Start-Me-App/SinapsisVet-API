<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">

  <title>Certificado - {{$title}}</title>
  <style>
@font-face {
  font-family: 'Quicksand';
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: url("../storage/fonts/Quicksand-Regular.ttf") format('truetype');
 }
 @font-face {
  font-family: 'Quicksand';
  font-style: normal;
  font-weight: 700;
  font-display: swap;
  src: url("../storage/fonts/Quicksand-Bold.ttf") format('truetype');
 }
 @font-face {
  font-family: 'Quicksand';
  font-style: normal;
  font-weight: 300;
  font-display: swap;
  src: url("../storage/fonts/Quicksand-Light.ttf") format('truetype');
 }
 @font-face {
  font-family: 'Quicksand';
  font-style: normal;
  font-weight: 500;
  font-display: swap;
  src: url("../storage/fonts/Quicksand-Medium.ttf") format('truetype');
 }
 @font-face {
  font-family: 'Quicksand';
  font-style: normal;
  font-weight: 600;
  font-display: swap;
  src: url("../storage/fonts/Quicksand-SemiBold.ttf") format('truetype');
 }
    html { margin: 0px; padding: 0px;}
    body {
      margin: 0;
      padding: 0;
      font-family: 'Quicksand', sans-serif; 
      background-color: #fff;
      width: 100%;
      height: 100%;
    }

    /* MAIN CONTAINER:
       - We fix a max-width of 1000px (smaller than 1600px).
       - Keep the aspect ratio of 1600 / 1131.
       - Use background-size: contain so we never cut the image. */
    .main-container {
        width:100%;
        height:100%;
        max-width: 1600px;
        max-height: 1131px;
        background-image: url("{{ public_path('images/background.jpg') }}");
        background-repeat: no-repeat;
        background-position: center;
        background-size: contain;    /* Show the entire image */
    }

    /* CONTENT WRAPPER:
       - Absolutely positioned inside .main-container to fill it entirely.
       - Using Flexbox to center everything horizontally and (optionally) vertically. */
    .content-wrapper {
      position: absolute;
      top: 160px;
      left: 0;
      width: 100%;
      height: 100%;

      display: flex;                 /* Flex layout */
      flex-direction: column;        /* Stack items vertically */
      justify-content: center;       /* Center vertically within container */
      align-items: center;           /* Center horizontally */
      gap: 0px;                      /* Spacing between elements */

      padding: 30px;
      box-sizing: border-box;
      text-align: center;            /* Ensure text is centered */
    }

    .main-title {
      margin-top: 0;
      margin-bottom: 0;
      font-size: 3rem;
      color: #543670;
      font-weight: bold;
    }

    .subtitle {
      font-size: 1.5rem;
      color: #543670;
      margin-bottom: 10px;
    }

    .certifica-text {
      font-size: 2rem;
      margin-top: 0px;
      margin-bottom: 50px;
      color: #333;
    }

    .student-name {
      font-size: 1.4rem;
      font-weight: bold;
      position: relative;
      margin: 20px 0;
    }
    .student-name::after {
      content: "";
      display: block;
      width: 500px;             /* Adjust length of dotted line */
      margin: 10px auto 0;
      height: 1px;
      border-bottom: 2px dotted #7777;
    }

    .course-text {
      font-size: 1.1rem;
      line-height: 1.4;      
      color: #333;
      font-weight: 500;
    }

    /* Centered signature */
    .signature-container {
      display: flex;
      flex-direction: column;
      align-items: center; /* center horizontally */
      margin-top: 30px;
    }
    .signature-container img {
      width: 100px;
      height: auto;
    }
    .signature-name {
      margin-top: 5px;
      font-size: 0.9rem;
      color: #333;
    }
  </style>
</head>
<body>
  <div class="main-container">
    <div class="content-wrapper">


      <h1 class="main-title">{{$title}}</h1>
      <div class="subtitle">{{$subtitle}}</div>
      <div class="certifica-text">Se certifica que</div>

      <!-- Replace (Nombre) with a Blade variable: {{ $student }} -->
      <div class="student-name">{{$student}}</div>

      <!-- You can also replace the text below with dynamic content -->
      <div class="course-text">
        Ha {{$type}} al curso {{$title}} <strong>impartido por Sinapsis Vet</strong> celebrado de forma virtual el {{$date}}
      </div>

      <!-- Firma -->
      <div class="signature-container">
        <!-- Replace with your dynamic signature if needed -->
        <img 
          src="{{ public_path('images/firma.png') }}"
          alt="Firma"
        />
        <div class="signature-name">Fernando C Pellegrino</div>
      </div>
    </div>
  </div>
</body>
</html>
