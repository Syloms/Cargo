class UserVerification {
  // Personal Information
  String? firstName;
  String? middleName;
  String? lastName;
  String? suffix;
  
  // Permanent Address
  String? permRegion;
  String? permProvince;
  String? permCity;
  String? permBarangay;
  String? permZipCode;
  String? permAddressLine;
  
  // Present Address
  bool sameAsPermanent;
  String? presRegion;
  String? presProvince;
  String? presCity;
  String? presBarangay;
  String? presZipCode;
  String? presAddressLine;
  
  // Contact Information
  String? email;
  String? mobileNumber;
  String? gender;
  DateTime? dateOfBirth;
  String? nationality;
  
  // Documents
  String? idFrontPhoto;
  String? idBackPhoto;
  String? selfiePhoto;

  UserVerification({
    this.firstName,
    this.middleName,
    this.lastName,
    this.suffix,
    this.permRegion,
    this.permProvince,
    this.permCity,
    this.permBarangay,
    this.permZipCode,
    this.permAddressLine,
    this.sameAsPermanent = false,
    this.presRegion,
    this.presProvince,
    this.presCity,
    this.presBarangay,
    this.presZipCode,
    this.presAddressLine,
    this.email,
    this.mobileNumber,
    this.gender,
    this.dateOfBirth,
    this.nationality,
    this.idFrontPhoto,
    this.idBackPhoto,
    this.selfiePhoto,
  });

  Map<String, dynamic> toJson() {
    return {
      'firstName': firstName,
      'middleName': middleName,
      'lastName': lastName,
      'suffix': suffix,
      'permRegion': permRegion,
      'permProvince': permProvince,
      'permCity': permCity,
      'permBarangay': permBarangay,
      'permZipCode': permZipCode,
      'permAddressLine': permAddressLine,
      'sameAsPermanent': sameAsPermanent,
      'presRegion': presRegion,
      'presProvince': presProvince,
      'presCity': presCity,
      'presBarangay': presBarangay,
      'presZipCode': presZipCode,
      'presAddressLine': presAddressLine,
      'email': email,
      'mobileNumber': mobileNumber,
      'gender': gender,
      'dateOfBirth': dateOfBirth?.toIso8601String(),
      'nationality': nationality,
      'idFrontPhoto': idFrontPhoto,
      'idBackPhoto': idBackPhoto,
      'selfiePhoto': selfiePhoto,
    };
  }
}