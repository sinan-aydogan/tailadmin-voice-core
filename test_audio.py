from pydub import AudioSegment
import sys
if len(sys.argv) > 1:
    print(AudioSegment.from_file(sys.argv[1]))
