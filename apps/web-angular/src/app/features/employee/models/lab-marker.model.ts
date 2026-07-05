export type LabMarkerStatus = 'unter Bereich' | 'im Orientierungsbereich' | 'über Bereich';

export type LabMarkerGroup = 'blutbild' | 'immun' | 'mikro' | 'sonstige';

export interface LabMarker {
  markerKey: string;
  name: string;
  unit: string;
  value: number;
  status: LabMarkerStatus;
  isHighlighted: boolean;
  low: number | null;
  high: number | null;
  group: LabMarkerGroup;
}
