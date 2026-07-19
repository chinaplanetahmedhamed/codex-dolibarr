export const palette = {
  navy: '#102F49',
  navyDeep: '#082239',
  navySoft: '#173E56',
  brandRed: '#D91436',
  brandRedLight: '#FF6B7E',
  brandRedSoft: '#FCE7EC',
  cyan: '#0EA5D2',
  blue: '#07579A',
  teal: '#167A74',
  green: '#26B566',
  greenSoft: '#E7F8EF',
  orange: '#F59E0B',
  orangeSoft: '#FFF4D8',
  red: '#D94B45',
  redSoft: '#FDEBEA',
  background: '#F4F8FA',
  surface: '#FFFFFF',
  surfaceAlt: '#EDF7FC',
  border: '#DBE6EB',
  text: '#172535',
  muted: '#6D818C',
  white: '#FFFFFF',
  black: '#0B1720',
} as const;

export const radii = {
  small: 10,
  medium: 16,
  large: 24,
  pill: 999,
} as const;

export const shadows = {
  card: {
    shadowColor: '#102F49',
    shadowOpacity: 0.08,
    shadowRadius: 16,
    shadowOffset: { width: 0, height: 7 },
    elevation: 3,
  },
};
