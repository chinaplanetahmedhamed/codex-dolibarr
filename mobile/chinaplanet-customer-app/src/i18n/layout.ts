import type { FlexAlignType } from 'react-native';

export function textAlignment(isRtl: boolean): 'right' | 'left' {
  return isRtl ? 'right' : 'left';
}

export function contentAlignment(isRtl: boolean): FlexAlignType {
  return isRtl ? 'flex-end' : 'flex-start';
}

export function leadingRowDirection(_isRtl: boolean): 'row' {
  // The provider sets the platform direction to rtl/ltr. React Native then
  // places the first row child on the logical leading edge automatically.
  return 'row';
}
