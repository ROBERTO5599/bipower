import re

file_path = r'C:\proyectos\bipower\app\Http\Controllers\ResumenEjecutivoController.php'

with open(file_path, 'r', encoding='utf-8') as f:
    text = f.read()

# Replace the date definitions
text = text.replace(
    "$fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->toDateString());",
    "$fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->toDateString()) . ' 00:00:00';"
)

text = text.replace(
    "$fechaFin = $request->input('fecha_fin', now()->toDateString());",
    "$fechaFin = $request->input('fecha_fin', now()->toDateString()) . ' 23:59:59';"
)

text = text.replace(
    "$fechaFinSiguiente = date('Y-m-d', strtotime($fechaFin . ' +1 day'));",
    "$fechaFinSiguiente = $fechaFin;"
)

# And replace `>= :fechaDel AND [...] < :fechaAlSig`
text = re.sub(
    r">= :fechaDel(\d*)[\s\n]+AND\s+([\w\.]+)\s*<\s*:fechaAlSig\1",
    r"BETWEEN :fechaDel\1 AND :fechaAlSig\1",
    text
)

# Replace the same on the same line if missed
text = re.sub(
    r"(\w+\.\w+)\s*>=\s*:fechaDel(\d*)\s*AND\s+\1\s*<\s*:fechaAlSig\2",
    r"\1 BETWEEN :fechaDel\2 AND :fechaAlSig\2",
    text
)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(text)

print('Done script')
