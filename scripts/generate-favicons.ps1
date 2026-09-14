# Generates public/favicon.ico and public/apple-touch-icon.png
# from a single reticle drawing routine so all sizes stay visually consistent
# with public/favicon.svg. Re-run whenever the mark changes.
Add-Type -AssemblyName System.Drawing

$publicDir = Join-Path $PSScriptRoot '..\public'
$gold      = [System.Drawing.Color]::FromArgb(217, 174, 82)  # #D9AE52
$dark      = [System.Drawing.Color]::FromArgb(27, 31, 39)    # #1B1F27

function New-ReticleBitmap {
    param(
        [int]$Size
    )

    $bmp = New-Object System.Drawing.Bitmap($Size, $Size, [System.Drawing.Imaging.PixelFormat]::Format32bppArgb)
    $g   = [System.Drawing.Graphics]::FromImage($bmp)
    $g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias

    # Rounded-square dark tile
    $radius = [Math]::Round($Size * 0.19)
    $path   = New-Object System.Drawing.Drawing2D.GraphicsPath
    $d      = $radius * 2
    $path.AddArc(0, 0, $d, $d, 180, 90)
    $path.AddArc($Size - $d - 1, 0, $d, $d, 270, 90)
    $path.AddArc($Size - $d - 1, $Size - $d - 1, $d, $d, 0, 90)
    $path.AddArc(0, $Size - $d - 1, $d, $d, 90, 90)
    $path.CloseFigure()
    $fill = New-Object System.Drawing.SolidBrush $dark
    $g.FillPath($fill, $path)
    $fill.Dispose()
    $path.Dispose()

    # Coordinates on a 64x64 canvas, scaled to $Size
    $scale = $Size / 64.0
    $penOuter = New-Object System.Drawing.Pen ($gold, [Math]::Max(1, 3 * $scale))
    $penInner = New-Object System.Drawing.Pen ($gold, [Math]::Max(1, 2 * $scale))
    $penTick  = New-Object System.Drawing.Pen ($gold, [Math]::Max(1, 3 * $scale))
    $penTick.StartCap = [System.Drawing.Drawing2D.LineCap]::Round
    $penTick.EndCap   = [System.Drawing.Drawing2D.LineCap]::Round

    # Outer ring (r=26 on a 64px canvas, centred at 32,32)
    $g.DrawEllipse($penOuter, (32 - 26) * $scale, (32 - 26) * $scale, 52 * $scale, 52 * $scale)
    # Inner ring
    $g.DrawEllipse($penInner, (32 - 12) * $scale, (32 - 12) * $scale, 24 * $scale, 24 * $scale)
    # Ticks
    $g.DrawLine($penTick, 32 * $scale, 4  * $scale, 32 * $scale, 18 * $scale)
    $g.DrawLine($penTick, 32 * $scale, 46 * $scale, 32 * $scale, 60 * $scale)
    $g.DrawLine($penTick, 4  * $scale, 32 * $scale, 18 * $scale, 32 * $scale)
    $g.DrawLine($penTick, 46 * $scale, 32 * $scale, 60 * $scale, 32 * $scale)
    # Centre dot (r=3.5 on 64px canvas)
    $dotBrush = New-Object System.Drawing.SolidBrush $gold
    $g.FillEllipse($dotBrush, (32 - 3.5) * $scale, (32 - 3.5) * $scale, 7 * $scale, 7 * $scale)

    $dotBrush.Dispose()
    $penOuter.Dispose()
    $penInner.Dispose()
    $penTick.Dispose()
    $g.Dispose()

    return $bmp
}

# apple-touch-icon: iOS home-screen icon, 180x180 PNG
$apple = New-ReticleBitmap -Size 180
$applePath = Join-Path $publicDir 'apple-touch-icon.png'
$apple.Save($applePath, [System.Drawing.Imaging.ImageFormat]::Png)
$apple.Dispose()
Write-Host "Wrote $applePath"

# favicon.ico: modern PNG-in-ICO container with two sizes (16 + 32)
# ICO file layout: 6-byte header + N * 16-byte directory entries + N images.
$sizes = @(16, 32, 48)
$images = @()
foreach ($size in $sizes) {
    $bmp = New-ReticleBitmap -Size $size
    $ms  = New-Object System.IO.MemoryStream
    $bmp.Save($ms, [System.Drawing.Imaging.ImageFormat]::Png)
    $bmp.Dispose()
    $images += ,@{ Size = $size; Bytes = $ms.ToArray() }
    $ms.Dispose()
}

$icoPath = Join-Path $publicDir 'favicon.ico'
$stream  = [System.IO.File]::Open($icoPath, [System.IO.FileMode]::Create)
$writer  = New-Object System.IO.BinaryWriter($stream)

# ICONDIR
$writer.Write([UInt16]0)          # reserved
$writer.Write([UInt16]1)          # type: 1 = icon
$writer.Write([UInt16]$images.Count)

# ICONDIRENTRY (16 bytes each)
$offset = 6 + (16 * $images.Count)
foreach ($img in $images) {
    $s = if ($img.Size -ge 256) { 0 } else { $img.Size }
    $writer.Write([Byte]$s)                # width  (0 = 256)
    $writer.Write([Byte]$s)                # height (0 = 256)
    $writer.Write([Byte]0)                 # colour palette (0 = none)
    $writer.Write([Byte]0)                 # reserved
    $writer.Write([UInt16]1)               # colour planes
    $writer.Write([UInt16]32)              # bits per pixel
    $writer.Write([UInt32]$img.Bytes.Length)
    $writer.Write([UInt32]$offset)
    $offset += $img.Bytes.Length
}
# Image data
foreach ($img in $images) {
    $writer.Write($img.Bytes)
}

$writer.Flush()
$writer.Close()
$stream.Close()
Write-Host "Wrote $icoPath"
