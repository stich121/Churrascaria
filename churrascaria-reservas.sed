[Version]
Class=IEXPRESS
SEDVersion=3

[Options]
PackagePurpose=InstallApp
ShowInstallProgramWindow=0
HideExtractAnimation=1
UseLongFileName=1
InsideCompressed=0
CAB_FixedSize=0
CAB_ResvCodeSigning=0
RebootMode=N
InstallPrompt=
DisplayLicense=
FinishMessage=
TargetName=%TARGET_EXE%
FriendlyName=%FRIENDLY_NAME%
AppLaunched=%LAUNCHER%
PostInstallCmd=<None>
AdminQuietInstCmd=
UserQuietInstCmd=
SourceFiles=SourceFiles

[SourceFiles]
SourceFiles0=%SOURCE_FOLDER%

[SourceFiles0]
%FILE0%=
%FILE1%=
%FILE2%=
%FILE3%=
%FILE4%=
%FILE5%=

[Strings]
TARGET_EXE="C:\Users\mathe\Documents\Site de reservas para Churrascaria\ChurrascariaPampulhaReservas.exe"
FRIENDLY_NAME="Churrascaria Pampulha Reservas"
SOURCE_FOLDER="C:\Users\mathe\Documents\Site de reservas para Churrascaria"
LAUNCHER="launch-reservas.cmd"
FILE0="launch-reservas.cmd"
FILE1="index.html"
FILE2="styles.css"
FILE3="app.js"
FILE4="supabase.schema.sql"
FILE5="README.md"
