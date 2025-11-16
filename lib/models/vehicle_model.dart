class VehicleModel {
  final String name;
  final String category;
  final String location;
  final String price;
  final String image;
  final double rating;
  final String? seats;

  VehicleModel({
    required this.name,
    required this.category,
    required this.location,
    required this.price,
    required this.image,
    required this.rating,
    this.seats,
  });
}