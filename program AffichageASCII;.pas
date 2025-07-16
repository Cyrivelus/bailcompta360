program AffichageASCII;

uses
  Printer, Crt;

var
  i: Integer;
  ch: Char;
begin
  ClrScr;
  Writeln('Affichage des caracteres ASCII de 32 a 255 :');
  Writeln;

  // Affichage à l'écran
  for i := 32 to 255 do
  begin
    ch := Chr(i);
    Write(i:4, ': ', ch, '   ');
    if (i - 31) mod 6 = 0 then
      Writeln;
  end;
